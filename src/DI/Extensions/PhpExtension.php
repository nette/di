<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Extensions;

use DateTime;
use DateTimeZone;
use Nette;


/**
 * PHP directives definition.
 */
class PhpExtension extends Nette\DI\CompilerExtension
{

	public function afterCompile(Nette\PhpGenerator\ClassType $class)
	{
		$initialize = $class->getMethod('initialize');
		$timezone = null;
		foreach ($this->getConfig() as $name => $value) {
			if ($value === NULL) {
				continue;

			} elseif (!is_scalar($value)) {
				throw new Nette\InvalidStateException("Configuration value for directive '$name' is not scalar.");

			} elseif ($name === 'include_path') {
				$initialize->addBody('set_include_path(?);', [str_replace(';', PATH_SEPARATOR, $value)]);

			} elseif ($name === 'ignore_user_abort') {
				$initialize->addBody('ignore_user_abort(?);', [$value]);

			} elseif ($name === 'max_execution_time') {
				$initialize->addBody('set_time_limit(?);', [$value]);

			} elseif ($name === 'date.timezone') {
				$timezone = new DateTimeZone($value);
				$initialize->addBody('date_default_timezone_set(?);', [$value]);

			} elseif (function_exists('ini_set')) {
				$initialize->addBody('ini_set(?, ?);', [$name, $value]);

			} elseif (ini_get($name) != $value) { // intentionally ==
				throw new Nette\NotSupportedException('Required function ini_set() is disabled.');
			}
		}
		// Fix all incorrect DateTime object when timezone was specified
		if ($timezone) {
			array_walk_recursive($this->getContainerBuilder()->parameters, function (&$value) use ($timezone) {
				if ($value instanceof DateTime) {
					$value = $this->recreateDateTime($value, $timezone);
				}
			});
		}
	}

	private function recreateDateTime(DateTime $value, DateTimeZone $timezone)
	{
		return new DateTime($value->format('Y-m-d H:i:s.u'), $timezone); // forgot timezone and recreate in proper one
	}

}
