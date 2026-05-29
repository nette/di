<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Extensions;

use Nette;
use function is_int, is_string, sprintf;


/**
 * Enables registration of additional compiler extensions via configuration.
 */
class ExtensionsExtension extends Nette\DI\CompilerExtension
{
	public function getConfigSchema(): Nette\Schema\Schema
	{
		return Nette\Schema\Expect::arrayOf('string|Nette\DI\Definitions\Statement');
	}


	public function loadConfiguration(): void
	{
		foreach ((array) $this->getConfig() as $name => $class) {
			if (is_int($name)) {
				$name = null;
			}

			$args = [];
			if ($class instanceof Nette\DI\Definitions\Statement) {
				[$class, $args] = [$class->getEntity(), $class->arguments];
			}

			if (!is_string($class) || !is_a($class, Nette\DI\CompilerExtension::class, allow_string: true)) {
				throw new Nette\DI\InvalidConfigurationException(sprintf(
					"Extension '%s' not found or is not Nette\\DI\\CompilerExtension descendant.",
					is_string($class) ? $class : get_debug_type($class),
				));
			}

			$this->compiler->addExtension($name, (new \ReflectionClass($class))->newInstanceArgs($args));
		}
	}
}
