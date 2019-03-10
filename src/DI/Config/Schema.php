<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Config;


/**
 * Configuration normalization & merging & validation.
 */
interface Schema
{
	/**
	 * Configuration normalization & merging.
	 * @return mixed
	 */
	function flatten(array $configs, array $path = []);

	/**
	 * Configuration validation and finalization.
	 * @return mixed
	 */
	function complete($value, array $path = []);

	/**
	 * @return mixed
	 */
	function getDefault(array $path);
}
