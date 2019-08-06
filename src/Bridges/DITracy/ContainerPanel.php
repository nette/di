<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Bridges\DITracy;

use Nette;
use Nette\DI\Container;
use Tracy;


/**
 * Dependency injection container panel for Debugger Bar.
 */
class ContainerPanel implements Tracy\IBarPanel
{
	use Nette\SmartObject;

	/** @var float|null */
	public static $compilationTime;

	/** @var Nette\DI\Container */
	private $container;

	/** @var float|null */
	private $elapsedTime;


	public function __construct(Container $container)
	{
		$this->container = $container;
		$this->elapsedTime = self::$compilationTime ? microtime(true) - self::$compilationTime : null;
	}


	/**
	 * Renders tab.
	 */
	public function getTab(): string
	{
		ob_start(function () {});
		$elapsedTime = $this->elapsedTime;
		require __DIR__ . '/templates/ContainerPanel.tab.phtml';
		return ob_get_clean();
	}


	/**
	 * Renders panel.
	 */
	public function getPanel(): string
	{
		$container = $this->container;
		$rc = new \ReflectionClass($container);
		$file = $rc->getFileName();
		$tags = [];
		$instances = $this->getContainerProperty('instances');
		$wiring = $this->getContainerProperty('wiring');
		$types = [];
		foreach ($rc->getMethods() as $method) {
			if (preg_match('#^createService(.+)#', $method->getName(), $m) && $method->getReturnType()) {
				$types[lcfirst(str_replace('__', '.', $m[1]))] = $method->getReturnType()->getName();
			}
		}
		$types = $this->getContainerProperty('types') + $types;
		ksort($types);
		foreach ($this->getContainerProperty('tags') as $tag => $tmp) {
			foreach ($tmp as $service => $val) {
				$tags[$service][$tag] = $val;
			}
		}

		ob_start(function () {});
		require __DIR__ . '/templates/ContainerPanel.panel.phtml';
		return ob_get_clean();
	}


	private function getContainerProperty(string $name)
	{
		$prop = (new \ReflectionClass(Nette\DI\Container::class))->getProperty($name);
		$prop->setAccessible(true);
		return $prop->getValue($this->container);
	}
}
