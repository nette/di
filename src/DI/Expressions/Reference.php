<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Expressions;

use Nette\DI;
use Nette\DI\Expression;
use Nette\PhpGenerator\Literal;
use function sprintf;


/**
 * Reference to service. Either by name or by type or reference to the 'self' service.
 */
final class Reference extends Expression
{
	use Chaining;

	public const Self = 'self';

	#[\Deprecated('use Reference::Self')]
	public const SELF = self::Self;


	/**
	 * Creates a type-based reference (resolved by class name rather than service name).
	 */
	public static function fromType(string $value): static
	{
		if (!str_contains($value, '\\')) {
			$value = '\\' . $value;
		}

		return new static($value);
	}


	public function __construct(
		private readonly string $value,
		public readonly bool $shared = true,
	) {
	}


	public function getValue(): string
	{
		return $this->value;
	}


	public function isName(): bool
	{
		return !str_contains($this->value, '\\') && $this->value !== self::Self;
	}


	public function isType(): bool
	{
		return str_contains($this->value, '\\');
	}


	public function isSelf(): bool
	{
		return $this->value === self::Self;
	}


	/**
	 * Returns the reference normalized to 'self' or to a named reference (or leaves it type-based
	 * when it cannot be resolved yet) and checks existence of the service.
	 */
	public function complete(DI\Compiler\Resolver $resolver): self
	{
		if ($this->isSelf()) {
			return $this;

		} elseif ($this->isName()) {
			if (!$resolver->getContainerBuilder()->hasDefinition($this->value)) {
				throw new DI\ServiceCreationException(sprintf("Reference to missing service '%s'.", $this->value));
			}

			return $this->value === $resolver->getCurrentService()?->getName(throw: false)
				? new self(self::Self, $this->shared)
				: $this;
		}

		try {
			return $resolver->getByType($this->value);
		} catch (DI\NotAllowedDuringResolvingException) {
			return $this;
		}
	}


	public function resolveType(DI\Compiler\Resolver $resolver): ?string
	{
		if ($this->isSelf()) {
			return $resolver->getCurrentServiceType();

		} elseif ($this->isType()) {
			return ltrim($this->value, '\\');
		}

		$def = $resolver->getContainerBuilder()->getDefinition($this->value);
		if (!$def->getType()) {
			$resolver->resolveDefinition($def);
		}

		return $def->getType();
	}


	public function generateCode(DI\Compiler\PhpGenerator $generator): string
	{
		return match (true) {
			$this->isSelf() => '$service',
			$this->value === DI\ContainerBuilder::ThisContainer => '$this',
			!$this->shared => $generator->formatPhp('?->?()', [new Literal('$this'), DI\Container::getMethodName($this->value)]),
			default => $generator->formatPhp('$this->getService(?)', [$this->value]),
		};
	}


	public function transformValues(callable $cb): static
	{
		return $this;
	}
}
