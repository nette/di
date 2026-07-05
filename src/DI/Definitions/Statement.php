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
use function in_array, is_array, is_string, sprintf;


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
	 * Returns normalized entity: literal, Class, Reference, [Class, member], [, globalFunc], [Reference, member], [Statement, member]
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
			$item = $resolver->normalizeReference($item);
		}

		return $entity;
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
