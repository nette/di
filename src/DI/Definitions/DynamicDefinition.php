<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Definitions;

use Nette;
use Nette\DI\PhpGenerator;
use Nette\DI\ServiceCreationException;


/**
 * Dynamic service definition.
 */
final class DynamicDefinition extends Definition
{
	/**
	 * @return static
	 */
	public function setType(?string $type)
	{
		return parent::setType($type);
	}


	public function resolveType(Nette\DI\Resolver $resolver): void
	{
	}


	public function complete(Nette\DI\Resolver $resolver): void
	{
		if (!$this->getType()) {
			throw new ServiceCreationException('Type is missing in definition of service.');
		}
	}


	public function generateMethod(Nette\PhpGenerator\Method $method, PhpGenerator $generator): void
	{
		$method->setReturnType('void')
			->setBody(
				'throw new Nette\\DI\\ServiceCreationException(?);',
				["Unable to create dynamic service '{$this->getName()}', it must be added using addService()"]
			);
	}


	/**
	 * @deprecated
	 */
	public function isDynamic(): bool
	{
		return true;
	}
}
