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
				/** @var Nette\DI\CompilerExtension $rcExtension */
				$rcExtension = (new \ReflectionClass($class->getEntity()))->newInstanceArgs($class->arguments);
				$this->compiler->addExtension($name, $rcExtension);
			} else {
				$this->compiler->addExtension($name, new $class);
			}
		}
	}
}
