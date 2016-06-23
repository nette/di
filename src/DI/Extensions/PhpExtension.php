<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Extensions;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Nette;


/**
 * PHP directives definition.
 */
class PhpExtension extends Nette\DI\CompilerExtension
{

	public function loadConfiguration()
	{
		$config = $this->getConfig();
		if (isset($config['date.timezone'])) {
			$timezone = new DateTimeZone($config['date.timezone']);
			// Fix all incorrect DateTime object when timezone was specified
			array_walk_recursive($this->getContainerBuilder()->parameters, function (&$value) use ($timezone) {
				if ($value instanceof DateTime || $value instanceof DateTimeImmutable) {
					$value = $this->recreateDateTime($value, $timezone);
				}
			});
		}
	}

	public function afterCompile(Nette\PhpGenerator\ClassType $class)
	{
		$initialize = $class->getMethod('initialize');
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
				$initialize->addBody('date_default_timezone_set(?);', [$value]);

			} elseif (function_exists('ini_set')) {
				$initialize->addBody('ini_set(?, ?);', [$name, $value]);

			} elseif (ini_get($name) != $value) { // intentionally ==
				throw new Nette\NotSupportedException('Required function ini_set() is disabled.');
			}
		}
	}

	private function recreateDateTime(DateTimeInterface $value, DateTimeZone $timezone)
	{
		return new DateTimeImmutable($value->format('Y-m-d H:i:s.u'), $timezone); // forgot timezone and recreate in proper one
	}

}
