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
	 * Formats PHP code that evaluates the expression.
	 */
	abstract public function generateCode(Nette\DI\Compiler\PhpGenerator $generator): string;
}
