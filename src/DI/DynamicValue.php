<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI;


/**
 * Marks a parameter value (at any nesting level) as dynamic, i.e. supplied to the container at
 * runtime instead of being compiled into it. It is addressed by the dotted path of its position
 * (e.g. 'db.password'). The optional value is the default used when no runtime value is supplied.
 */
final class DynamicValue
{
	public function __construct(
		public readonly mixed $value = null,
	) {
	}


	/**
	 * The value identifies neither the marker (its position does) nor the compiled container, so
	 * that a per-request value cannot bust its cache. It therefore serializes as an empty object;
	 * a caller caching by config and relying on the default must include it in its own cache key.
	 */
	public function __serialize(): array
	{
		return [];
	}
}
