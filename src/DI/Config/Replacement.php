<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Config;

use function is_array;


/**
 * Wraps a value marked by the replacing operator ! that is not an array and thus cannot carry
 * the PREVENT_MERGING marker. It replaces the value of earlier layers instead of merging with it.
 * @internal
 */
final class Replacement
{
	public function __construct(
		public readonly mixed $value,
	) {
	}


	/**
	 * Unwraps the replacements in the configuration; an array gets the PREVENT_MERGING marker instead.
	 */
	public static function resolve(mixed $config): mixed
	{
		if ($config instanceof self) {
			$config = self::resolve($config->value);
			if (is_array($config)) {
				$config[Helpers::PREVENT_MERGING] = true;
			}

			return $config;

		} elseif (is_array($config)) {
			return array_map(self::resolve(...), $config);
		}

		return $config;
	}
}
