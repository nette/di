<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Definitions;

use Nette;


/**
 * Reference to service. Either by name or by type or reference to the 'self' service.
 */
final class Reference
{
	use Nette\SmartObject;

	public const Self = 'self';

	/** @deprecated use Reference::Self */
	public const SELF = self::Self;

	private string $value;


	public static function fromType(string $value): static
	{
		if (!str_contains($value, '\\')) {
			$value = '\\' . $value;
		}

		return new static($value);
	}


	public function __construct(string $value)
	{
		$this->value = $value;
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
}
