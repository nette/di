<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Bridges\DITracy;

use Nette;
use Nette\DI\Container;
use Tracy;


/**
 * Dependency injection container panel for Debugger Bar.
 */
class ContainerPanel implements Tracy\IBarPanel
{
	public static ?float $compilationTime = null;
	private Nette\DI\Container $container;
	private ?float $elapsedTime;


	public function __construct(Container $container)
	{
		$this->container = $container;
		$this->elapsedTime = self::$compilationTime
			? microtime(as_float: true) - self::$compilationTime
			: null;
	}


	/**
	 * Renders tab.
	 */
	public function getTab(): string
	{
		return Nette\Utils\Helpers::capture(function () {
			$elapsedTime = $this->elapsedTime;
			require __DIR__ . '/dist/tab.phtml';
		});
	}


	/**
	 * Renders panel.
	 */
	public function getPanel(): string
	{
		$rc = new \ReflectionClass($this->container);

		$services = $this->container->getServiceTypes();
		ksort($services, SORT_NATURAL);

		$tags = [];
		foreach ($services as $name => $type) {
			$serviceTags = $this->container->getServiceTags($name);
			if ($serviceTags) {
				$tags[$name] = $serviceTags;
			}
		}

		$autowiredCache = $typeKnownCache = [];
		$autowireStatus = function (string $type, string $name) use (&$autowiredCache, &$typeKnownCache): string {
			$autowiredCache[$type] ??= array_flip($this->container->findAutowired($type));
			if (isset($autowiredCache[$type][$name])) {
				return 'yes';
			}
			$typeKnownCache[$type] ??= $this->container->findByType($type) !== [];
			return $typeKnownCache[$type] ? 'no' : '?';
		};

		return Nette\Utils\Helpers::capture(function () use ($rc, $tags, $services, $autowireStatus) {
			$container = $this->container;
			$file = $rc->getFileName();
			$instances = $this->container->getInstantiatedServices();
			$parameters = $rc->getMethod('getStaticParameters')->getDeclaringClass()->getName() === Container::class
				? null
				: $container->getParameters();
			require __DIR__ . '/dist/panel.phtml';
		});
	}
}
