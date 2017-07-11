<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Extensions;

use Nette;


/**
 * Constant definitions.
 */
final class ConstantsExtension extends Nette\DI\CompilerExtension
{
	public function afterCompile(Nette\PhpGenerator\ClassType $class)
	{
		foreach ($this->getConfig() as $name => $value) {
			$class->getMethod('initialize')->addBody('define(?, ?);', [$name, $value]);
		}
	}
}
