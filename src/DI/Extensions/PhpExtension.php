<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Extensions;

use Nette;


/**
 * PHP directives definition.
 */
final class PhpExtension extends Nette\DI\CompilerExtension
{
	public function getConfigSchema(): Nette\Schema\Schema
	{
		return Nette\Schema\Expect::arrayOf('scalar');
	}


	public function afterCompile(Nette\PhpGenerator\ClassType $class)
	{
		$initialize = $class->getMethod('initialize');
		foreach ($this->getConfig() as $name => $value) {
			if ($value === null) {
				continue;

			} elseif ($name === 'include_path') {
				$initialize->addBody('set_include_path(?);', [str_replace(';', PATH_SEPARATOR, $value)]);

			} elseif ($name === 'ignore_user_abort') {
				$initialize->addBody('ignore_user_abort(?);', [$value]);

			} elseif ($name === 'max_execution_time') {
				$initialize->addBody('set_time_limit(?);', [$value]);

			} elseif ($name === 'date.timezone') {
				$initialize->addBody('date_default_timezone_set(?);', [$value]);

			} elseif (function_exists('ini_set')) {
				$initialize->addBody('ini_set(?, ?);', [$name, $value === false ? '0' : (string) $value]);

			} elseif (ini_get($name) != $value) { // intentionally ==
				throw new Nette\NotSupportedException('Required function ini_set() is disabled.');
			}
		}
	}
}
