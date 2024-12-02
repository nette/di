<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Definitions;

use Nette;
use Nette\DI;
use Nette\DI\Resolver;
use Nette\DI\ServiceCreationException;
use Nette\PhpGenerator as Php;
use Nette\Utils\Callback;
use Nette\Utils\Validators;
use function array_keys, class_exists, explode, is_array, is_string, str_contains, str_starts_with, substr;


/**
 * Assignment or calling statement.
 *
 * @property string|array|Definition|Reference|null $entity
 */
final class Statement extends Expression implements Nette\Schema\DynamicParameter
{
	use Nette\SmartObject;

	public array $arguments;
	private string|array|Definition|Reference|null $entity;


	public function __construct(string|array|Definition|Reference|null $entity, array $arguments = [])
	{
		if (
			$entity !== null
			&& !is_string($entity) // Class, @service, not, tags, types, PHP literal, entity::member
			&& !$entity instanceof Definition
			&& !$entity instanceof Reference
			&& !(is_array($entity)
				&& array_keys($entity) === [0, 1]
				&& (is_string($entity[0])
					|| $entity[0] instanceof self
					|| $entity[0] instanceof Reference
					|| $entity[0] instanceof Definition)
			)) {
			throw new Nette\InvalidArgumentException('Argument is not valid Statement entity.');
		}

		// normalize Class::method to [Class, method]
		if (is_string($entity) && str_contains($entity, '::') && !str_contains($entity, '?')) {
			$entity = explode('::', $entity, 2);
		}

		if (is_string($entity) && str_starts_with($entity, '@')) { // normalize @service to Reference
			$entity = new Reference(substr($entity, 1));
		} elseif (is_array($entity) && is_string($entity[0]) && str_starts_with($entity[0], '@')) {
			$entity[0] = new Reference(substr($entity[0], 1));
		}

		$this->entity = $entity;
		$this->arguments = $arguments;
	}


	public function getEntity(): string|array|Definition|Reference|null
	{
		return $this->entity;
	}


	public function resolveType(Resolver $resolver): ?string
	{
		$entity = $this->normalizeEntity($resolver);

		if (is_array($entity)) {
			if ($entity[0] instanceof Expression) {
				$entity[0] = $entity[0]->resolveType($resolver);
				if (!$entity[0]) {
					return null;
				}
			}

			try {
				$reflection = Callback::toReflection($entity[0] === '' ? $entity[1] : $entity);
				assert($reflection instanceof \ReflectionMethod || $reflection instanceof \ReflectionFunction);
				$refClass = $reflection instanceof \ReflectionMethod
					? $reflection->getDeclaringClass()
					: null;
			} catch (\ReflectionException $e) {
				$refClass = $reflection = null;
			}

			if (isset($e) || ($refClass && (!$reflection->isPublic()
				|| ($refClass->isTrait() && !$reflection->isStatic())
			))) {
				throw new ServiceCreationException(sprintf('Method %s() is not callable.', Callback::toString($entity)), 0, $e ?? null);
			}

			$resolver->addDependency($reflection);

			$type = Nette\Utils\Type::fromReflection($reflection) ?? ($annotation = DI\Helpers::getReturnTypeAnnotation($reflection));
			if ($type && !in_array($type->getSingleName(), ['object', 'mixed'], strict: true)) {
				if (isset($annotation)) {
					trigger_error('Annotation @return should be replaced with native return type at ' . Callback::toString($entity), E_USER_DEPRECATED);
				}

				return DI\Helpers::ensureClassType(
					$type,
					sprintf('return type of %s()', Callback::toString($entity)),
					allowNullable: true,
				);
			}

			return null;

		} elseif ($entity instanceof Expression) {
			return $entity->resolveType($resolver);

		} elseif (is_string($entity)) { // class
			if (!class_exists($entity)) {
				throw new ServiceCreationException(sprintf(
					interface_exists($entity)
						? "Interface %s can not be used as 'create' or 'factory', did you mean 'implement'?"
						: "Class '%s' not found.",
					$entity,
				));
			}

			return $entity;
		}

		return null;
	}


	public function complete(Resolver $resolver): void
	{
		$entity = $this->normalizeEntity($resolver);
		$this->convertReferences($resolver);
		$arguments = $this->arguments;

		switch (true) {
			case is_string($entity) && str_contains($entity, '?'): // PHP literal
				break;

			case $entity === 'not':
				if (count($arguments) !== 1) {
					throw new ServiceCreationException(sprintf('Function %s() expects 1 parameter, %s given.', $entity, count($arguments)));
				}

				$this->entity = ['', '!'];
				break;

			case $entity === 'bool':
			case $entity === 'int':
			case $entity === 'float':
			case $entity === 'string':
				if (count($arguments) !== 1) {
					throw new ServiceCreationException(sprintf('Function %s() expects 1 parameter, %s given.', $entity, count($arguments)));
				}

				$arguments = [$arguments[0], $entity];
				$this->entity = [DI\Helpers::class, 'convertType'];
				break;

			case is_string($entity): // create class
				if (!class_exists($entity)) {
					throw new ServiceCreationException(sprintf("Class '%s' not found.", $entity));
				} elseif ((new \ReflectionClass($entity))->isAbstract()) {
					throw new ServiceCreationException(sprintf('Class %s is abstract.', $entity));
				} elseif (($rm = (new \ReflectionClass($entity))->getConstructor()) !== null && !$rm->isPublic()) {
					throw new ServiceCreationException(sprintf('Class %s has %s constructor.', $entity, $rm->isProtected() ? 'protected' : 'private'));
				} elseif ($constructor = (new \ReflectionClass($entity))->getConstructor()) {
					$arguments = $resolver->autowireServices($constructor, $arguments);
					$resolver->addDependency($constructor);
				} elseif ($arguments) {
					throw new ServiceCreationException(sprintf(
						'Unable to pass arguments, class %s has no constructor.',
						$entity,
					));
				}

				break;

			case $entity instanceof Reference:
				if ($arguments) {
					$e = $resolver->completeException(new ServiceCreationException(sprintf('Parameters were passed to reference @%s, although references cannot have any parameters.', $entity->getValue())), $resolver->getCurrentService());
					trigger_error($e->getMessage(), E_USER_DEPRECATED);
				}
				$this->entity = [new Reference(DI\ContainerBuilder::ThisContainer), DI\Container::getMethodName($entity->getValue())];
				break;

			case is_array($entity):
				if (!preg_match('#^\$?(\\\?' . Php\Helpers::ReIdentifier . ')+(\[\])?$#D', $entity[1])) {
					throw new ServiceCreationException(sprintf(
						"Expected function, method or property name, '%s' given.",
						$entity[1],
					));
				}

				switch (true) {
					case $entity[0] === '': // function call
						if (!function_exists($entity[1])) {
							throw new ServiceCreationException(sprintf("Function %s doesn't exist.", $entity[1]));
						}

						$rf = new \ReflectionFunction($entity[1]);
						$arguments = $resolver->autowireServices($rf, $arguments);
						$resolver->addDependency($rf);
						break;

					case $entity[0] instanceof self:
						$entity[0]->complete($resolver);
						// break omitted

					case is_string($entity[0]): // static method call
					case $entity[0] instanceof Reference:
						if ($entity[1][0] === '$') { // property getter, setter or appender
							Validators::assert($arguments, 'list:0..1', "setup arguments for '" . Callback::toString($entity) . "'");
							if (!$arguments && str_ends_with($entity[1], '[]')) {
								throw new ServiceCreationException(sprintf('Missing argument for %s.', $entity[1]));
							}
						} elseif (
							$type = ($entity[0] instanceof Expression ? $entity[0] : new self($entity[0]))->resolveType($resolver)
						) {
							$rc = new \ReflectionClass($type);
							if ($rc->hasMethod($entity[1])) {
								$rm = $rc->getMethod($entity[1]);
								if (!$rm->isPublic()) {
									throw new ServiceCreationException(sprintf('%s::%s() is not callable.', $type, $entity[1]));
								}

								$arguments = $resolver->autowireServices($rm, $arguments);
								$resolver->addDependency($rm);
							}
						}
				}
		}

		try {
			$this->arguments = $this->completeArguments($resolver, $arguments);
		} catch (ServiceCreationException $e) {
			if (!str_contains($e->getMessage(), ' (used in')) {
				$e->setMessage($e->getMessage() . " (used in {$resolver->entityToString($entity)})");
			}

			throw $e;
		}
	}


	public function completeArguments(Resolver $resolver, array $arguments): array
	{
		array_walk_recursive($arguments, function (&$val) use ($resolver): void {
			if ($val instanceof self) {
				if ($val->entity === 'typed' || $val->entity === 'tagged') {
					$services = [];
					$current = $resolver->getCurrentService()?->getName();
					foreach ($val->arguments as $argument) {
						foreach ($val->entity === 'tagged' ? $resolver->getContainerBuilder()->findByTag($argument) : $resolver->getContainerBuilder()->findAutowired($argument) as $name => $foo) {
							if ($name !== $current) {
								$services[] = new Reference($name);
							}
						}
					}

					$val = $this->completeArguments($resolver, $services);
				} else {
					$val->complete($resolver);
				}
			} elseif ($val instanceof Definition || $val instanceof Reference) {
				$val = (new self($val))->normalizeEntity($resolver);
			}
		});
		return $arguments;
	}


	/** Returns literal, Class, Reference, [Class, member], [, globalFunc], [Reference, member], [Statement, member] */
	private function normalizeEntity(Resolver $resolver): string|array|Reference|null
	{
		if (is_array($this->entity)) {
			$item = &$this->entity[0];
		} else {
			$item = &$this->entity;
		}

		if ($item instanceof Definition) {
			if ($resolver->getContainerBuilder()->getDefinition($item->getName()) !== $item) {
				throw new ServiceCreationException(sprintf("Service '%s' does not match the expected service.", $item->getName()));

			}
			$item = new Reference($item->getName());
		}

		if ($item instanceof Reference) {
			$item->complete($resolver);
		}

		return $this->entity;
	}


	private function convertReferences(Resolver $resolver): void
	{
		array_walk_recursive($this->arguments, function (&$val) use ($resolver): void {
			if (is_string($val) && strlen($val) > 1 && $val[0] === '@' && $val[1] !== '@') {
				$pair = explode('::', substr($val, 1), 2);
				if (!isset($pair[1])) { // @service
					$val = new Reference($pair[0]);
				} elseif (preg_match('#^[A-Z][a-zA-Z0-9_]*$#D', $pair[1])) { // @service::CONSTANT
					$val = DI\ContainerBuilder::literal((new Reference($pair[0]))->resolveType($resolver) . '::' . $pair[1]);
				} else { // @service::property
					$val = new self([new Reference($pair[0]), '$' . $pair[1]]);
				}
			} elseif (is_string($val) && str_starts_with($val, '@@')) { // escaped text @@
				$val = substr($val, 1);
			}
		});
	}


	/**
	 * Formats PHP code for class instantiating, function calling or property setting in PHP.
	 */
	public function generateCode(DI\PhpGenerator $generator): string
	{
		$entity = $this->entity;
		$arguments = $this->arguments;

		switch (true) {
			case is_string($entity) && str_contains($entity, '?'): // PHP literal
				return $generator->formatPhp($entity, $arguments);

			case is_string($entity): // create class
				return $arguments
					? $generator->formatPhp("new $entity(...?:)", [$arguments])
					: $generator->formatPhp("new $entity", []);

			case is_array($entity):
				switch (true) {
					case $entity[1][0] === '$': // property getter, setter or appender
						$name = substr($entity[1], 1);
						if ($append = (str_ends_with($name, '[]'))) {
							$name = substr($name, 0, -2);
						}

						$prop = $entity[0] instanceof Reference
							? $generator->formatPhp('?->?', [$entity[0], $name])
							: $generator->formatPhp('?::$?', [$entity[0], $name]);
						return $arguments
							? $generator->formatPhp(($append ? '?[]' : '?') . ' = ?', [new Php\Literal($prop), $arguments[0]])
							: $prop;

					case $entity[0] instanceof self:
						$inner = $generator->formatPhp('?', [$entity[0]]);
						if (str_starts_with($inner, 'new ')) {
							$inner = "($inner)";
						}

						return $generator->formatPhp('?->?(...?:)', [new Php\Literal($inner), $entity[1], $arguments]);

					case $entity[0] instanceof Reference:
						return $generator->formatPhp('?->?(...?:)', [$entity[0], $entity[1], $arguments]);

					case $entity[0] === '': // function call
						return $generator->formatPhp('?(...?:)', [new Php\Literal($entity[1]), $arguments]);

					case is_string($entity[0]): // static method call
						return $generator->formatPhp('?::?(...?:)', [new Php\Literal($entity[0]), $entity[1], $arguments]);
				}
		}

		throw new Nette\InvalidStateException;
	}
}


class_exists(Nette\DI\Statement::class);
