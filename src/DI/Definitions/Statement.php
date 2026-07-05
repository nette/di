<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Definitions;

use Nette;
use Nette\DI;
use Nette\DI\Compiler\Resolver;
use Nette\DI\Definition;
use Nette\DI\Expression;
use Nette\DI\Expressions\Reference;
use Nette\DI\ServiceCreationException;
use Nette\PhpGenerator as Php;
use Nette\Utils\Callback;
use Nette\Utils\Validators;
use function count, in_array, is_array, is_string, sprintf;


/**
 * Assignment or calling statement.
 * It also serves as the base class of the specialized Nette\DI\Expressions\* nodes for backward compatibility.
 * @property string|array{string|Expression,string}|Definition|Reference|null $entity
 */
final class Statement extends Expression
{
	use Nette\SmartObject;

	public function __construct(
		private string|array|Definition|Reference|null $entity,
		/** @var array<mixed> */
		public array $arguments = [],
	) {
		if (
			$entity !== null
			&& !is_string($entity) // Class, @service, not, tags, types, PHP literal, entity::member
			&& !$entity instanceof Definition
			&& !$entity instanceof Reference
			&& !(is_array($entity)
				&& array_keys($entity) === [0, 1]
				&& (is_string($entity[0])
					|| $entity[0] instanceof Expression
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
	}


	/** @return string|array{string|Expression, string}|Definition|Reference|null */
	public function getEntity(): string|array|Definition|Reference|null
	{
		return $this->entity;
	}


	public function resolveType(Resolver $resolver): ?string
	{
		$entity = $this->normalizeEntity($resolver);

		if ($this->arguments === Resolver::getFirstClassCallable()) {
			return \Closure::class;

		} elseif (is_array($entity)) {
			if ($entity[0] instanceof Expression) {
				$entity[0] = $entity[0]->resolveType($resolver);
				if (!$entity[0]) {
					return null;
				}
			}

			try {
				$reflection = Callback::toReflection($entity[0] === '' ? $entity[1] : $entity);
				$refClass = $reflection instanceof \ReflectionMethod
					? $reflection->getDeclaringClass()
					: null;
			} catch (\ReflectionException $e) {
				throw new ServiceCreationException(sprintf('Method %s() is not callable.', Callback::toString($entity)), 0, $e);
			}

			if ($reflection instanceof \ReflectionMethod && $refClass && (!$reflection->isPublic()
				|| ($refClass->isTrait() && !$reflection->isStatic())
			)) {
				throw new ServiceCreationException(sprintf('Method %s() is not callable.', Callback::toString($entity)));
			}

			$resolver->addDependency($reflection);

			return ($type = Nette\Utils\Type::fromReflection($reflection)) && !in_array($type->getSingleName(), ['object', 'mixed'], strict: true)
				? DI\Helpers::ensureClassType(
					$type,
					sprintf('return type of %s()', Callback::toString($entity)),
					allowNullable: true,
				)
				: null;

		} elseif ($entity instanceof Reference) { // alias or factory
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


	/**
	 * Returns a completed (resolved and autowired) version of the statement. The original statement is left unchanged.
	 */
	public function complete(Resolver $resolver): static
	{
		$entity = $this->normalizeEntity($resolver);
		$arguments = $this->arguments;

		switch (true) {
			case $this->arguments === Resolver::getFirstClassCallable():
				if (!is_array($entity) || !Php\Helpers::isIdentifier($entity[1])) {
					throw new ServiceCreationException(sprintf('Cannot create closure for %s(...)', Callback::toString($entity)));
				}
				if ($entity[0] instanceof self) {
					$entity[0] = $entity[0]->complete($resolver);
				}
				break;

			case is_string($entity) && str_contains($entity, '?'): // PHP literal
				break;

			case $entity === 'not':
				if (count($arguments) !== 1) {
					throw new ServiceCreationException(sprintf('Function %s() expects 1 parameter, %s given.', $entity, count($arguments)));
				}

				$entity = ['', '!'];
				break;

			case $entity === 'bool':
			case $entity === 'int':
			case $entity === 'float':
			case $entity === 'string':
				if (count($arguments) !== 1) {
					throw new ServiceCreationException(sprintf('Function %s() expects 1 parameter, %s given.', $entity, count($arguments)));
				}

				$arguments = [$arguments[0], $entity];
				$entity = [DI\Helpers::class, 'convertType'];
				break;

			case is_string($entity): // create class
				if (!class_exists($entity)) {
					throw new ServiceCreationException(sprintf("Class '%s' not found.", $entity));
				} elseif ((new \ReflectionClass($entity))->isAbstract()) {
					throw new ServiceCreationException(sprintf('Class %s is abstract.', $entity));
				} elseif (($rm = (new \ReflectionClass($entity))->getConstructor()) !== null && !$rm->isPublic()) {
					throw new ServiceCreationException(sprintf('Class %s has %s constructor.', $entity, $rm->isProtected() ? 'protected' : 'private'));
				} elseif ($constructor = (new \ReflectionClass($entity))->getConstructor()) {
					$arguments = $resolver->autowireArguments($constructor, $arguments, $resolver);
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
					throw $resolver->completeException(new ServiceCreationException(sprintf('Parameters were passed to reference @%s, although references cannot have any parameters.', $entity->getValue())), $resolver->getCurrentService());
				}

				$entity = [new Reference(DI\ContainerBuilder::ThisContainer), DI\Container::getMethodName($entity->getValue())];
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
						$arguments = $resolver->autowireArguments($rf, $arguments, $resolver);
						$resolver->addDependency($rf);
						break;

					case $entity[0] instanceof self:
						$entity[0] = $entity[0]->complete($resolver);
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

								$arguments = $resolver->autowireArguments($rm, $arguments, $resolver);
								$resolver->addDependency($rm);
							}
						}
				}
		}

		$arguments = $resolver->resolveArguments($arguments, $entity);
		return new self($entity, $arguments);
	}


	/**
	 * Returns normalized entity: literal, Class, Reference, [Class, member], [, globalFunc], [Reference, member], [Statement, member]
	 * @return string|array{string|Expression, string}|Reference|null
	 */
	private function normalizeEntity(Resolver $resolver): string|array|Reference|null
	{
		$entity = $this->entity;
		if (is_array($entity)) {
			$item = &$entity[0];
		} else {
			$item = &$entity;
		}

		if ($item instanceof Definition) {
			$name = $item->getName();
			if ($name === null || $resolver->getContainerBuilder()->getDefinition($name) !== $item) {
				throw new ServiceCreationException(sprintf("Service '%s' does not match the expected service.", $name));
			}
			$item = new Reference($name);
		}

		if ($item instanceof Reference) {
			$item = $item->complete($resolver);
		}

		return $entity;
	}


	public function transformValues(callable $cb): static
	{
		return new self($cb($this->entity), $cb($this->arguments));
	}


	/**
	 * Formats PHP code for class instantiating, function calling or property setting.
	 */
	public function generateCode(DI\Compiler\PhpGenerator $generator): string
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
