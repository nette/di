<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Expressions;

use Nette\DI\Compiler\PhpGenerator;
use Nette\DI\Compiler\Resolver;
use Nette\DI\Expression;
use Nette\DI\ServiceCreationException;
use function is_string, sprintf;


/**
 * Access to a class constant on the type of the target, e.g. @service::CONSTANT.
 */
final class ConstantFetch extends Expression
{
	public function __construct(
		public readonly Expression|string $target,
		public readonly string $name,
	) {
	}


	public function complete(Resolver $resolver): self
	{
		if (is_string($this->target)) {
			return $this;
		}

		$type = $this->target->resolveType($resolver);
		if ($type === null) {
			throw new ServiceCreationException(sprintf("Cannot resolve the type to read constant '%s'.", $this->name));
		}

		return new self($type, $this->name);
	}


	public function generateCode(PhpGenerator $generator): string
	{
		if (!is_string($this->target)) {
			throw new \LogicException('ConstantFetch must be completed before generating code.');
		}

		return $this->target . '::' . $this->name;
	}


	public function transformValues(callable $cb): static
	{
		$name = $cb($this->name);
		return new self($cb($this->target), is_string($name) ? $name : $this->name);
	}
}
