<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Definitions;

use Nette;
use Nette\DI\PhpGenerator;
use Nette\DI\Resolver;
use Nette\PhpGenerator as Php;


final class FunctionCallable extends Expression
{
	public function __construct(
		public string $function,
	) {
		if (!Php\Helpers::isIdentifier($function)) {
			throw new Nette\InvalidArgumentException("Function name '$function' is not valid.");
		}
	}


	public function resolveType(Resolver $resolver): ?string
	{
		return \Closure::class;
	}


	public function complete(Resolver $resolver): void
	{
	}


	public function generateCode(PhpGenerator $generator): string
	{
		return $this->function . '(...)';
	}
}
