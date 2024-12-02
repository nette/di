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


final class MethodCallable extends Expression
{
	public function __construct(
		public Expression|string $objectOrClass,
		public string $method,
	) {
		if (is_string($objectOrClass) && !Php\Helpers::isNamespaceIdentifier($objectOrClass)) {
			throw new Nette\InvalidArgumentException("Class name '$objectOrClass' is not valid.");
		}
		if (!Php\Helpers::isIdentifier($method)) {
			throw new Nette\InvalidArgumentException("Method name '$method' is not valid.");
		}
	}


	public function resolveType(Resolver $resolver): ?string
	{
		return \Closure::class;
	}


	public function complete(Resolver $resolver): void
	{
		if ($this->objectOrClass instanceof Expression) {
			$this->objectOrClass->complete($resolver);
		}
	}


	public function generateCode(PhpGenerator $generator): string
	{
		return is_string($this->objectOrClass)
			? $generator->formatPhp('?::?(...)', [new Php\Literal($this->objectOrClass), $this->method])
			: $generator->formatPhp('?->?(...)', [new Php\Literal($this->objectOrClass->generateCode($generator)), $this->method]);
	}
}
