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
		$entity = $resolver->normalizeEntity($this);

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
