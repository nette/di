<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI;

use Nette;


/**
 * Service not found exception.
 */
class MissingServiceException extends Nette\InvalidStateException
{
}


/**
 * Service creation exception.
 */
class ServiceCreationException extends Nette\InvalidStateException
{
}

/**
 * Max nesting includes of same file reached exception.
 */
class RecursiveIncludesException extends Nette\ArgumentOutOfRangeException
{
}
