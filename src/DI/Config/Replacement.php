<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Config;


/**
 * Wraps a value marked by the replacing operator ! that is not an array and thus cannot carry
 * the PREVENT_MERGING marker. It replaces the value of earlier layers instead of merging with it.
 * @internal
 */
final readonly class Replacement
{
	public function __construct(
		public mixed $value,
	) {
	}
}
