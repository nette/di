<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Config;

use Nette;


/**
 * Configuration helpers.
 */
final class Helpers
{
	use Nette\StaticClass;

	public const PREVENT_MERGING = '_prevent_merging';


	/**
	 * Merges configurations. Left has higher priority than right one.
	 * @return array|string
	 */
	public static function merge($left, $right)
	{
		if (is_array($left) && isset($left[self::PREVENT_MERGING])) {
			unset($left[self::PREVENT_MERGING]);
			return $left;
		}

		if (is_array($left) && is_array($right)) {
			foreach ($left as $key => $val) {
				if (is_int($key)) {
					$right[] = $val;
				} else {
					$right[$key] = static::merge($val, $right[$key] ?? null);
				}
			}
			return $right;

		} elseif ($left === null && is_array($right)) {
			return $right;

		} else {
			return $left;
		}
	}


	/**
	 * Return true if array prevents merging and removes this information.
	 * @return mixed
	 */
	public static function takeParent(&$data): bool
	{
		if (is_array($data) && isset($data[self::PREVENT_MERGING])) {
			unset($data[self::PREVENT_MERGING]);
			return true;
		}
		return false;
	}
}
