<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Expressions;

use Nette\DI\Expression;
use Nette\DI\Helpers;
use Nette\DI\ServiceCreationException;
use function count, sprintf;


/**
 * Built-in configuration function applied to a single value: not() and the lossless
 * casts bool()/int()/float()/string(). A shared home so that rarely-used special
 * functions do not each need their own expression class.
 */
final class SpecialFunction extends Expression
{
	public const Functions = ['not', 'bool', 'int', 'float', 'string'];


	public function __construct(
		/** one of self::Functions */
		public readonly string $function,
		/** @var array<mixed> */
		public readonly array $arguments,
	) {
	}


	public function complete(Resolver $resolver): self
	{
		if (count($this->arguments) !== 1) {
			throw new ServiceCreationException(sprintf('Function %s() expects 1 parameter, %s given.', $this->function, count($this->arguments)));
		}

		return new self($this->function, $resolver->resolveArguments($this->arguments, "$this->function()"));
	}


	public function generateCode(PhpGenerator $generator): string
	{
		return $this->function === 'not'
			? $generator->formatPhp('!(?)', $this->arguments)
			: $generator->formatPhp(Helpers::class . '::convertType(?, ?)', [$this->arguments[0], $this->function]);
	}


	public function transformValues(callable $cb): static
	{
		return new self($this->function, $cb($this->arguments));
	}
}
