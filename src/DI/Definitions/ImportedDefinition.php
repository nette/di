<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Definitions;

use Nette;


/**
 * Imported service injected to the container.
 */
final class ImportedDefinition extends Definition
{
	public function setType(?string $type): static
	{
		return parent::setType($type);
	}


	public function resolveType(Nette\DI\Resolver $resolver): void
	{
	}


	public function complete(Nette\DI\Resolver $resolver): void
	{
	}


	public function generateCode(Nette\DI\PhpGenerator $generator): string
	{
		return $generator->formatPhp(
			'throw new Nette\DI\ServiceCreationException(?);',
			["Unable to create imported service '{$this->getName()}', it must be added using addService()"],
		);
	}
}
