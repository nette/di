<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI;

use Nette;


/**
 * Expression that evaluates to a value when the container is built. It is resolved
 * to a type, completed (autowired and validated) and finally compiled to PHP code.
 */
abstract class Expression implements Nette\Schema\DynamicParameter
{
	/**
	 * Returns the class name the expression evaluates to, or null when it does not produce
	 * a resolvable service type (values, statements, casts, ...).
	 */
	public function resolveType(Nette\DI\Compiler\Resolver $resolver): ?string
	{
		return null;
	}


	/**
	 * Returns a completed (resolved, validated and autowired) version of the expression.
	 * The original expression is left unchanged.
	 */
	abstract public function complete(Nette\DI\Compiler\Resolver $resolver): self;


	/**
	 * Formats PHP code that evaluates the expression.
	 */
	abstract public function generateCode(Nette\DI\Compiler\PhpGenerator $generator): string;


	/**
	 * Returns a clone of the expression with all child values transformed by the callback.
	 * It is used for recursive processing of values in configuration (parameter expansion etc.).
	 */
	abstract public function transformValues(callable $cb): static;
}
