<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Expressions;

use Nette\DI;
use Nette\DI\Expression;


/**
 * Reference to service. Either by name or by type or reference to the 'self' service.
 */
final class Reference extends Expression
{
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
			default => $generator->formatPhp('$this->getService(?)', [$this->value]),
		};
	}
}
