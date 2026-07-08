<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Config;


/**
 * Adapter for reading and writing configuration files.
 */
interface Adapter
{
	/**
	 * Reads configuration from file.
	 * @return array<string, mixed>
	 */
	function load(string $file): array;
}
