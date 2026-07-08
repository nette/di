<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Expressions;


/**
 * The three forms of a property access. Distinguishes a read from an assignment of null.
 */
enum PropertyMode
{
	case Read;     // $obj->prop
	case Assign;   // $obj->prop = value
	case Append;   // $obj->prop[] = value
}
