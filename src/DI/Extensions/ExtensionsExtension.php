<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Extensions;

use Nette;
use Nette\DI\Attributes\Hook;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\Statement;
use Nette\DI\Phase;
use function get_debug_type, is_a, is_int, is_string, sprintf;


/**
 * Enables registration of additional compiler extensions via configuration.
 */
class ExtensionsExtension extends Nette\DI\CompilerExtension
{
	/** @var array<int|string, string|Statement> */
	protected $config = [];


	public function getConfigSchema(): Nette\Schema\Schema
	{
		return Nette\Schema\Expect::arrayOf('string|Nette\DI\Definitions\Statement');
	}


	#[Hook(Phase::Setup, after: ParametersExtension::class)]
	public function doSetup(ContainerBuilder $builder): void
	{
		foreach ($this->config as $name => $class) {
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
