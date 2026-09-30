<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Definitions;


/**
 * Reference to service. Either by name or by type or reference to the 'self' service.
 */
final class Reference
{
	public const Self = 'self';

	/** @deprecated use Reference::Self */
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


	/** @phpstan-assert-if-true class-string $this->getValue() */
	public function isType(): bool
	{
		return str_contains($this->value, '\\');
	}


	/** @phpstan-assert-if-true self::Self $this->getValue() */
	public function isSelf(): bool
	{
		return $this->value === self::Self;
	}
}
