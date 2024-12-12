<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI;

use Nette;


/**
 * The requested service was not found in the container.
 */
class MissingServiceException extends Nette\InvalidStateException
{
}


/**
 * Failed to create the service instance.
 */
class ServiceCreationException extends Nette\InvalidStateException
{
	public function setMessage(string $message): static
	{
		$this->message = $message;
		return $this;
	}
}


/**
 * Operation is not allowed while container is resolving dependencies.
 */
class NotAllowedDuringResolvingException extends Nette\InvalidStateException
{
}


/**
 * The DI container configuration is invalid.
 */
class InvalidConfigurationException extends Nette\InvalidStateException
{
}
