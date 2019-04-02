<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Extensions;

use Nette;


/**
 * DI extension.
 */
final class DIExtension extends Nette\DI\CompilerExtension
{
	/** @var bool */
	private $debugMode;

	/** @var int */
	private $time;


	public function __construct(bool $debugMode = false)
	{
		$this->debugMode = $debugMode;
		$this->time = microtime(true);

		$this->config = new class {
			/** @var bool */
			public $debugger;
			/** @var string[] */
			public $excluded = [];
			/** @var ?string */
			public $parentClass;
		};
		$this->config->debugger = interface_exists(\Tracy\IBarPanel::class);
	}


	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$builder->addExcludedClasses($this->config->excluded);
	}


	public function afterCompile(Nette\PhpGenerator\ClassType $class)
	{
		if ($this->config->parentClass) {
			$class->setExtends($this->config->parentClass);
		}

		if ($this->debugMode && $this->config->debugger) {
			$this->enableTracyIntegration($class);
		}

		$this->initializeTaggedServices($class);
	}


	private function initializeTaggedServices(Nette\PhpGenerator\ClassType $class): void
	{
		foreach (array_filter($this->getContainerBuilder()->findByTag('run')) as $name => $on) {
			trigger_error("Tag 'run' used in service '$name' definition is deprecated.", E_USER_DEPRECATED);
			$class->getMethod('initialize')->addBody('$this->getService(?);', [$name]);
		}
	}


	private function enableTracyIntegration(Nette\PhpGenerator\ClassType $class): void
	{
		Nette\Bridges\DITracy\ContainerPanel::$compilationTime = $this->time;
		$class->getMethod('initialize')->addBody($this->getContainerBuilder()->formatPhp('?;', [
			new Nette\DI\Definitions\Statement('@Tracy\Bar::addPanel', [new Nette\DI\Definitions\Statement(Nette\Bridges\DITracy\ContainerPanel::class)]),
		]));
	}
}
