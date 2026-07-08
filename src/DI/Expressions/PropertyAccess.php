<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Expressions;

use Nette\DI\Compiler\PhpGenerator;
use Nette\DI\Compiler\Resolver;
use Nette\DI\Definitions\Statement;
use Nette\DI\Expression;
use Nette\DI\ServiceCreationException;
use Nette\PhpGenerator as Php;
use Nette\Utils\Type;
use function class_exists, interface_exists, is_string, preg_match, sprintf;


/**
 * Property read ($obj->prop), assignment ($obj->prop = value) or append ($obj->prop[] = value).
 */
final class PropertyAccess extends Statement
{
	public function __construct(
		public readonly Expression|string $target,
		public readonly string $name,
		public readonly PropertyMode $mode = PropertyMode::Read,
		/** the assigned value; unused for a read */
		public readonly mixed $value = null,
	) {
		$this->arguments = $mode === PropertyMode::Read ? [] : [$value];
	}


	public function resolveType(Resolver $resolver): ?string
	{
		if ($this->mode !== PropertyMode::Read) {
			// an assignment (or append) evaluates to the assigned value
			return $this->value instanceof Expression ? $this->value->resolveType($resolver) : null;
		}

		$class = $this->target instanceof Expression ? $this->target->resolveType($resolver) : $this->target;
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

		$target = $this->target instanceof Expression
			? $this->target->complete($resolver)
			: $this->target;

		if ($this->mode === PropertyMode::Read) {
			return new self($target, $this->name, $this->mode);
		}

		[$value] = $resolver->resolveArguments([$this->value], $this->usedIn($target));
		return new self($target, $this->name, $this->mode, $value);
	}


	public function generateCode(PhpGenerator $generator): string
	{
		$prop = $this->target instanceof Reference
			? $generator->formatPhp('?->?', [$this->target, $this->name])
			: $generator->formatPhp('?::$?', [$this->target, $this->name]);

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


	/**
	 * Legacy Statement-shaped entity, for code inspecting completed definitions.
	 * @return array{Expression|string, string}
	 */
	public function getEntity(): array
	{
		return [$this->target, '$' . $this->name . ($this->mode === PropertyMode::Append ? '[]' : '')];
	}
}
