<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Extensions;

use Nette;


/**
 * PHP configurators.
 */
class ConfiguratorsExtension extends Nette\DI\CompilerExtension
{
	public function loadConfiguration()
	{
		foreach ($this->config as $callback) {
			$callback($this->compiler);
		}
	}
}
