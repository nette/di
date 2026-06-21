<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Bridges\DIPsr;

use Nette;
use Psr\Container\NotFoundExceptionInterface;


/**
 * No single service could be resolved for the given PSR-11 identifier.
 */
class NotFoundException extends Nette\InvalidStateException implements NotFoundExceptionInterface
{
}
