<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Definitions;

use Nette;
use Nette\DI\Definition;


/**
 * Imported service injected to the container.
 */
final class ImportedDefinition extends Definition
{
	public function setType(?string $type): static
	{
		return parent::setType($type);
	}


	public function resolveType(Nette\DI\Compiler\Resolver $resolver): void
	{
	}


	public function complete(Nette\DI\Compiler\Resolver $resolver): void
	{
	}


	public function generateCode(Nette\DI\Compiler\PhpGenerator $generator): string
	{
		return $generator->formatPhp(
			'throw new Nette\DI\ServiceCreationException(?);',
			["Unable to create imported service '{$this->getName()}', it must be added using addService()"],
		);
	}
}
