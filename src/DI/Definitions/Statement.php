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
use Nette\Utils\Validators;
use function in_array, is_array, is_string, sprintf;


/**
 * Assignment or calling statement.
 *
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
		return $this->specialize($entity)->resolveType($resolver);
	}


	/**
	 * Returns a completed (resolved and autowired) version of the statement. The original statement is left unchanged.
	 */
	public function complete(Resolver $resolver): DI\Expression
	{
		$entity = $this->normalizeEntity($resolver);
		if ($entity instanceof Reference && $this->arguments) {
			throw new ServiceCreationException(sprintf('Parameters were passed to reference @%s, although references cannot have any parameters.', $entity->getValue()));
		}
		return $this->specialize($entity)->complete($resolver);
	}


	/**
	 * Maps the statement's entity + arguments to the corresponding specialized expression node.
	 * The shared mapping used by complete() and generateCode().
	 */
	private function specialize(string|array|Reference $entity): DI\Expression
	{
		switch (true) {
			case is_string($entity) && str_contains($entity, '?'): // PHP literal
				return new DI\Expressions\PhpCode($entity, $this->arguments);

			case in_array($entity, DI\Expressions\SpecialFunction::Functions, true): // not(), int(), ...
				return new DI\Expressions\SpecialFunction($entity, $this->arguments);

			case is_string($entity): // create class
				return new DI\Expressions\Instantiation($entity, $this->arguments);

			case $entity instanceof Reference: // produce the referenced service fresh via the container factory
				return new Reference($entity->getValue(), shared: false);

			case is_array($entity) && is_string($entity[1]) && str_starts_with($entity[1], '$'): // property access
				$name = substr($entity[1], 1);
				$append = str_ends_with($name, '[]');
				if ($append) {
					$name = substr($name, 0, -2);
				}

				Validators::assert($this->arguments, 'list:0..1', "setup arguments for '$entity[1]'");
				if ($append && !$this->arguments) {
					throw new ServiceCreationException(sprintf('Missing argument for $%s[].', $name));
				}

				$mode = match (true) {
					$append => DI\Expressions\PropertyMode::Append,
					(bool) $this->arguments => DI\Expressions\PropertyMode::Assign,
					default => DI\Expressions\PropertyMode::Read,
				};
				return new DI\Expressions\PropertyAccess($entity[0], $name, $mode, $this->arguments[0] ?? null);

			default: // is_array: function, static or method call
				return new DI\Expressions\Call(
					$entity[0] === '' ? null : $entity[0],
					$entity[1],
					$this->arguments,
				);
		}
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
			$name = $item->getName(throw: false);
			if ($name === null || $resolver->getContainerBuilder()->getDefinition($name) !== $item) {
				throw new ServiceCreationException(sprintf("Service '%s' does not match the expected service.", $name));
			}
			$item = new Reference($name);
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
		if ($this->entity === null || $this->entity instanceof Definition) {
			throw new Nette\InvalidStateException;
		}

		return $this->specialize($this->entity)->generateCode($generator);
	}
}
