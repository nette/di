<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Expressions;


/**
 * Placeholder for an unbound argument in a partial function application (PHP 8.6+).
 */
enum ArgumentPlaceholder
{
	case Single;     // ?   a single deferred argument
	case Variadic;   // ... the remaining arguments
}
