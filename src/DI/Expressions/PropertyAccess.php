<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Expressions;

use Nette\DI\Compiler\{PhpGenerator, Resolver};
use Nette\DI\{Expression, ServiceCreationException};
use Nette\PhpGenerator as Php;
use Nette\Utils\Type;
use function is_string, sprintf;


/**
 * Property read ($obj->prop), assignment ($obj->prop = value) or append ($obj->prop[] = value).
 * A static property is read on a class name, or on the type of the target ($obj::$prop).
 */
final class PropertyAccess extends Expression
{
	public function __construct(
		public readonly Expression|string $target,
		public readonly string $name,
		public readonly PropertyMode $mode = PropertyMode::Read,
		/** the assigned value; unused for a read */
		public readonly mixed $value = null,
		/** a static property of the type of an expression target; a class name target is always static */
		public readonly bool $static = false,
	) {
	}


	public function resolveType(Resolver $resolver): ?string
	{
		if ($this->mode !== PropertyMode::Read) {
			// an assignment (or append) evaluates to the assigned value
			return $this->value instanceof Expression ? $this->value->resolveType($resolver) : null;
		}

		$class = $this->target instanceof Expression ? $this->target->resolveType($resolver) : $this->target;
		if ($this->target instanceof Expression && !$this->static) {
			$this->checkIsNotMethod($class);
		}

		if ($class === null
			|| (!class_exists($class) && !interface_exists($class))
			|| !($rc = new \ReflectionClass($class))->hasProperty($this->name)
		) {
			return null;
		}

		$name = Type::fromReflection($rc->getProperty($this->name))?->getSingleName();
		return $name !== null && (class_exists($name) || interface_exists($name)) ? $name : null;
	}


	public function complete(Resolver $resolver): self
	{
		if (!preg_match('#^(\\\?' . Php\Helpers::ReIdentifier . ')+$#D', $this->name)) {
			throw new ServiceCreationException(sprintf(
				"Expected function, method or property name, '%s' given.",
				$this->label(),
			));
		}

		$target = $this->target;
		if ($target instanceof Expression && $this->static) {
			$target = $target->resolveType($resolver) ?? throw new ServiceCreationException(sprintf(
				"Cannot resolve the type to read static property '%s'.",
				$this->label(),
			));
		} elseif ($target instanceof Expression) {
			$target = $target->complete($resolver);
		}

		if ($this->mode === PropertyMode::Read) {
			if ($target instanceof Expression) {
				$this->checkIsNotMethod($target->resolveType($resolver));
			}
			return new self($target, $this->name, $this->mode);
		}

		[$value] = $resolver->resolveArguments([$this->value], $this->usedIn($target));
		return new self($target, $this->name, $this->mode, $value);
	}


	/**
	 * A name written without parentheses reads a property; when the class has only a method of that name,
	 * it was most likely meant to be called.
	 */
	private function checkIsNotMethod(?string $class): void
	{
		if ($class === null || !class_exists($class)) {
			return;
		}

		$rc = new \ReflectionClass($class);
		if ($rc->hasMethod($this->name) && !$rc->hasProperty($this->name) && !$rc->hasMethod('__get')) {
			throw new ServiceCreationException(sprintf(
				"Class %s has no property '%s', only a method; write '%s()' to call it.",
				$class,
				$this->label(),
				$this->name,
			));
		}
	}


	public function generateCode(PhpGenerator $generator): string
	{
		$prop = is_string($this->target)
			? $generator->formatPhp('?::$?', [$this->target, $this->name])
			: $generator->formatPhp('?->?', [$this->target, $this->name]);

		return match ($this->mode) {
			PropertyMode::Read => $prop,
			PropertyMode::Assign => $generator->formatPhp('? = ?', [new Php\Literal($prop), $this->value]),
			PropertyMode::Append => $generator->formatPhp('?[] = ?', [new Php\Literal($prop), $this->value]),
		};
	}


	public function transformValues(callable $cb): static
	{
		$name = $cb($this->name);
		return new self(
			$cb($this->target),
			is_string($name) ? $name : $this->name,
			$this->mode,
			$cb($this->value),
			$this->static,
		);
	}


	/** Human-readable member name for error messages, e.g. $prop or $prop[]. */
	private function label(): string
	{
		return '$' . $this->name . ($this->mode === PropertyMode::Append ? '[]' : '');
	}


	/** Human-readable target for error messages, e.g. @svc::$prop, Foo::$prop or $prop. */
	private function usedIn(Expression|string $target): string
	{
		return match (true) {
			$target instanceof Reference => '@' . $target->getValue() . '::',
			is_string($target) => $target . '::',
			default => '',
		} . $this->label();
	}
}
