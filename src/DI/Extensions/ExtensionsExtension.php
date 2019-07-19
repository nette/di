<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Extensions;

use Nette;


/**
 * Enables registration of other extensions in $config file
 */
final class ExtensionsExtension extends Nette\DI\CompilerExtension
{
	public function getConfigSchema(): Nette\Schema\Schema
	{
		return Nette\Schema\Expect::arrayOf('string|Nette\DI\Definitions\Statement');
	}


	public function loadConfiguration()
	{
		foreach ($this->getConfig() as $name => $class) {
			if (is_int($name)) {
				$name = null;
			}
			if ($class instanceof Nette\DI\Definitions\Statement) {
				$rcExtension = (new \ReflectionClass($class->getEntity()))->newInstanceArgs($class->arguments);
				if ($rcExtension instanceof Nette\DI\CompilerExtension) {
					$this->compiler->addExtension($name, $rcExtension);
				} else {
					throw new Nette\DI\InvalidConfigurationException(
						"Statement extension expected type '" . Nette\DI\CompilerExtension::class . "', "
						. "but type of '" . \get_class($rcExtension) . "' given."
					);
				}
			} else {
				$this->compiler->addExtension($name, new $class);
			}
		}
	}
}
