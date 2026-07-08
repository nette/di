<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI;


/**
 * Compilation phases for DI container.
 */
enum Phase: string
{
	/** Environment preparation (parameters, extensions) */
	case Setup = 'setup';

	/** Unconditional service registration */
	case Register = 'register';

	/** Discovery and conditional registration */
	case Discover = 'discover';

	/** Modification of existing services */
	case Modify = 'modify';

	/** Modification of generated container class */
	case Compile = 'compile';
}
