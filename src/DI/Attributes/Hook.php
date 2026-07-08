<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Attributes;

use Attribute;
use Nette\DI\Phase;


/**
 * Marks a method as a compilation phase handler. A method handles exactly one phase;
 * the handler signature differs between phases (Compile receives the generated class,
 * the other phases receive the container builder).
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Hook
{
	/**
	 * @param Phase $phase Compilation phase
	 * @param string|string[]|null $before Extension(s) before which the hook should run
	 * @param string|string[]|null $after Extension(s) after which the hook should run
	 */
	public function __construct(
		public Phase $phase,
		public string|array|null $before = null,
		public string|array|null $after = null,
	) {
	}
}
