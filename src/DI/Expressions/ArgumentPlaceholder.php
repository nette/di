<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Expressions;


/**
 * Marker for an open argument slot. The context decides who fills it:
 * in a PartialCall it stays a runtime placeholder of a partial application (PHP 8.6+);
 * in an Instantiation/Call it means "autowire this position" (as if the argument was omitted)
 * and is gone after complete() - this is what the DSL wire() without arguments produces.
 */
enum ArgumentPlaceholder
{
	case Single;     // ?   a single deferred / open argument
	case Variadic;   // ... the remaining arguments
}
