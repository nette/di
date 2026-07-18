<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Config;

use Nette;
use function is_array;


/**
 * Configuration helpers.
 */
final class Helpers
{
	use Nette\StaticClass;

	public const PREVENT_MERGING = '_prevent_merging';


	/**
	 * Merges configurations. Left has higher priority than right one.
	 */
	public static function merge(mixed $left, mixed $right): mixed
	{
		if (is_array($left) && isset($left[self::PREVENT_MERGING])) {
			unset($left[self::PREVENT_MERGING]);
			return $left;
		}

		if (is_array($left) && is_array($right)) {
			$index = 0;
			foreach ($left as $key => $val) {
				if ($key === $index) {
					$right[] = $val;
					$index++;
				} else {
					$right[$key] = static::merge($val, $right[$key] ?? null);
				}
			}

			return $right;
		}

		return $left === null && is_array($right) ? $right : $left;
	}


	/** @deprecated */
	public static function takeParent(mixed &$data): bool
	{
		if (is_array($data) && isset($data[self::PREVENT_MERGING])) {
			unset($data[self::PREVENT_MERGING]);
			return true;
		}

		return false;
	}
}
