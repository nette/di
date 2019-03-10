<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Config;

use Nette;


/**
 * Default implementation of Schema.
 */
final class Expect implements Schema
{
	use Nette\SmartObject;

	/**
	 * Configuration normalization & merging.
	 */
	public function flatten(array $configs, array $path = [])
	{
		$flat = [];
		foreach ($configs as $config) {
			$flat = Helpers::merge($config, $flat);
		}
		return $flat;
	}


	/**
	 * Configuration validation and finalization.
	 */
	public function complete($value, array $path = [])
	{
		return $value;
	}


	public function getDefault(array $path)
	{
	}
}
