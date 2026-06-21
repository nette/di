<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Bridges\DIPsr;

use Nette;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;


class ContainerException extends Nette\InvalidStateException implements ContainerExceptionInterface
{
}


class NotFoundException extends Nette\InvalidStateException implements NotFoundExceptionInterface
{
}
