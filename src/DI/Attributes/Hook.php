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
	public function __construct(
		/** Compilation phase */
		public Phase $phase,
		/** @var string|string[]|null Extension(s) before which the hook should run */
		public string|array|null $before = null,
		/** @var string|string[]|null Extension(s) after which the hook should run */
		public string|array|null $after = null,
	) {
	}
}
