<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI;

use Nette;
use ReflectionClass;


/**
 * Cache dependencies checker.
 */
class DependencyChecker
{
	use Nette\SmartObject;

	/** @var array */
	private $dependencies = [];


	/**
	 * Adds dependencies to the list.
	 * @return self
	 */
	public function add(array $deps)
	{
		$this->dependencies = array_merge($this->dependencies, $deps);
		return $this;
	}


	/**
	 * Exports dependencies.
	 * @return array
	 */
	public function export()
	{
		$files = array_filter($this->dependencies);
		$files = @array_map('filemtime', array_combine($files, $files)); // @ - file may not exist
		return $files;
	}


	/**
	 * Are dependencies expired?
	 * @return bool
	 */
	public static function isExpired($files)
	{
		$current = @array_map('filemtime', array_combine($tmp = array_keys($files), $tmp)); // @ - files may not exist
		return $files !== $current;
	}

}
