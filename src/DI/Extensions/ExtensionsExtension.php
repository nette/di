<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Extensions;

use Nette;


/**
 * Enables registration of other extensions in $config file
 *
 * @author  Vojtech Dobes
 */
class ExtensionsExtension extends Nette\DI\CompilerExtension
{

	public function loadConfiguration()
	{
		foreach ($this->getConfig() as $name => $class) {
			if ($class instanceof \stdClass) {
				$rc = Nette\Reflection\ClassType::from($class->value);
				$this->compiler->addExtension($name, $rc->newInstanceArgs($class->attributes));
			} else {
				$this->compiler->addExtension($name, new $class);
			}
		}
	}

}
