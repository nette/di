<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Expressions;

use Nette\DI\Compiler\PhpGenerator;
use Nette\DI\Compiler\Resolver;
use Nette\DI\Definitions\Statement;
use function is_string;


/**
 * Piece of PHP code with ? placeholders substituted by arguments.
 */
final class PhpCode extends Statement
{
	public function __construct(
		public readonly string $code,
		/** @var array<mixed> */
		public array $arguments = [],
	) {
	}


	public function complete(Resolver $resolver): self
	{
		$arguments = $resolver->resolveArguments($this->arguments, $this->code);
		return new self($this->code, $arguments);
	}


	public function generateCode(PhpGenerator $generator): string
	{
		return $generator->formatPhp($this->code, $this->arguments);
	}


	public function transformValues(callable $cb): static
	{
		$code = $cb($this->code);
		return new self(is_string($code) ? $code : $this->code, $cb($this->arguments));
	}


	public function getEntity(): string
	{
		return $this->code;
	}
}
