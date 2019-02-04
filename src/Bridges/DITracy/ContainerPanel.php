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

	/** @var int */
	public static $compilationTime;

	/** @var Nette\DI\Container */
	private $container;

	/** @var int|null */
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
		$instances = $this->getContainerProperty('instances');
		$file = (new \ReflectionClass($container))->getFileName();
		$tags = [];
		$meta = $this->getContainerProperty('meta');
		$services = $meta[Container::SERVICES];
		ksort($services);
		if (isset($meta[Container::TAGS])) {
			foreach ($meta[Container::TAGS] as $tag => $tmp) {
				foreach ($tmp as $service => $val) {
					$tags[$service][$tag] = $val;
				}
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
