<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Definitions;

use Nette\DI;



/**
 * Reference to service. Either by name or by type or reference to the 'self' service.
 */
final class Reference extends Expression
{
	public const Self = 'self';

	#[\Deprecated('use Reference::Self')]
	public const SELF = self::Self;

	private string $value;

	private ?string $tag;


	public static function fromType(string $value, ?string $tag = null): static
	{
		if (!str_contains($value, '\\')) {
			$value = '\\' . $value;
		}

		return new static($value, $tag);
	}


	public function __construct(string $value, ?string $tag = null)
	{
		$this->value = $value;
		$this->tag = $tag;
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


	public function resolveType(DI\Resolver $resolver): ?string
	{
		if ($this->isSelf()) {
			return $resolver->getCurrentService(type: true);

		} elseif ($this->isType()) {
			return ltrim($this->value, '\\');
		}

		$def = $resolver->getContainerBuilder()->getDefinition($this->value);
		if (!$def->getType()) {
			$resolver->resolveDefinition($def);
		}

		return $def->getType();
	}


	/**
	 * Normalizes reference to 'self' or named reference (or leaves it typed if it is not possible during resolving) and checks existence of service.
	 */
	public function complete(DI\Resolver $resolver): void
	{
		if ($this->isSelf()) {
			return;

		} elseif ($this->isType()) {
			try {
				$reference = $resolver->getByType($this->value, $this->tag);
				$this->value = $reference->value;
			} catch (DI\NotAllowedDuringResolvingException) {
			}
			return;
		}

		if (!$resolver->getContainerBuilder()->hasDefinition($this->value)) {
			throw new DI\ServiceCreationException(sprintf("Reference to missing service '%s'.", $this->value));
		}

		if ($this->value === $resolver->getCurrentService()?->getName()) {
			$this->value = self::Self;
		}
	}


	public function generateCode(DI\PhpGenerator $generator): string
	{
		return match (true) {
			$this->isSelf() => '$service',
			$this->value === DI\ContainerBuilder::ThisContainer => '$this',
			default => $generator->formatPhp('$this->getService(?)', [$this->value]),
		};
	}
}
