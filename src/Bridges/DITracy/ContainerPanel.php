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
		$this->elapsedTime = self::$compilationTime
			? microtime(true) - self::$compilationTime
			: null;
	}


	/**
	 * Renders tab.
	 */
	public function getTab(): string
	{
		return Nette\Utils\Helpers::capture(function () {
			$elapsedTime = $this->elapsedTime;
			require __DIR__ . '/templates/ContainerPanel.tab.phtml';
		});
	}


	/**
	 * Renders panel.
	 */
	public function getPanel(): string
	{
		$methods = (fn() => $this->methods)->bindTo($this->container, Container::class)();
		$services = [];
		foreach ($methods as $name => $foo) {
			$name = lcfirst(str_replace('__', '.', substr($name, 13)));
			$services[$name] = $this->container->getServiceType($name);
		}
		ksort($services, SORT_NATURAL);

		$propertyTags = (fn() => $this->tags)->bindTo($this->container, $this->container)();
		$tags = [];
		foreach ($propertyTags as $tag => $tmp) {
			foreach ($tmp as $service => $val) {
				$tags[$service][$tag] = $val;
			}
		}

		return Nette\Utils\Helpers::capture(function () use ($tags, $services) {
			$container = $this->container;
			$rc = (new \ReflectionClass($this->container));
			$file = $rc->getFileName();
			$instances = (fn() => $this->instances)->bindTo($this->container, Container::class)();
			$wiring = (fn() => $this->wiring)->bindTo($this->container, $this->container)();
			$parameters = $rc->getMethod('getStaticParameters')->getDeclaringClass()->getName() === Container::class
				? null
				: $container->getParameters();
			require __DIR__ . '/templates/ContainerPanel.panel.phtml';
		});
	}
}
