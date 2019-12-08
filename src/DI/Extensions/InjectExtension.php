<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Extensions;

use Nette;
use Nette\DI;
use Nette\DI\Definitions;
use Nette\Utils\Reflection;


/**
 * Calls inject methods and fills @inject properties.
 */
final class InjectExtension extends DI\CompilerExtension
{
	public const TAG_INJECT = 'nette.inject';


	public function getConfigSchema(): Nette\Schema\Schema
	{
		return Nette\Schema\Expect::structure([]);
	}


	public function beforeCompile()
	{
		foreach ($this->getContainerBuilder()->getDefinitions() as $def) {
			if ($def->getTag(self::TAG_INJECT)) {
				$def = $def instanceof Definitions\FactoryDefinition ? $def->getResultDefinition() : $def;
				if ($def instanceof Definitions\ServiceDefinition) {
					$this->updateDefinition($def);
				}
			}
		}
	}


	private function updateDefinition(Definitions\ServiceDefinition $def): void
	{
		$resolver = new DI\Resolver($this->getContainerBuilder());
		$class = $resolver->resolveEntityType($def->getFactory()) ?: $def->getType();
		$setups = $def->getSetup();

		foreach (self::getInjectProperties($class) as $property => $type) {
			$builder = $this->getContainerBuilder();
			$inject = new Definitions\Statement('$' . $property, [Definitions\Reference::fromType((string) $type)]);
			foreach ($setups as $key => $setup) {
				if ($setup->getEntity() === $inject->getEntity()) {
					$inject = $setup;
					$builder = null;
					unset($setups[$key]);
				}
			}
			self::checkType($class, $property, $type, $builder);
			array_unshift($setups, $inject);
		}

		foreach (array_reverse(self::getInjectMethods($class)) as $method) {
			$inject = new Definitions\Statement($method);
			foreach ($setups as $key => $setup) {
				if ($setup->getEntity() === $inject->getEntity()) {
					$inject = $setup;
					unset($setups[$key]);
				}
			}
			array_unshift($setups, $inject);
		}

		$def->setSetup($setups);
	}


	/**
	 * Generates list of inject methods.
	 * @internal
	 */
	public static function getInjectMethods(string $class): array
	{
		$res = [];
		foreach (get_class_methods($class) as $name) {
			if (substr($name, 0, 6) === 'inject') {
				$res[$name] = (new \ReflectionMethod($class, $name))->getDeclaringClass()->getName();
			}
		}
		uksort($res, function (string $a, string $b) use ($res): int {
			return $res[$a] === $res[$b]
				? strcmp($a, $b)
				: (is_a($res[$a], $res[$b], true) ? 1 : -1);
		});
		return array_keys($res);
	}


	/**
	 * Generates list of properties with annotation @inject.
	 * @internal
	 */
	public static function getInjectProperties(string $class): array
	{
		$res = [];
		foreach (get_class_vars($class) as $name => $foo) {
			$rp = new \ReflectionProperty($class, $name);
			if (DI\Helpers::parseAnnotation($rp, 'inject') !== null) {
				if ($type = Reflection::getPropertyType($rp)) {
				} elseif ($type = DI\Helpers::parseAnnotation($rp, 'var')) {
					$type = Reflection::expandClassName($type, Reflection::getPropertyDeclaringClass($rp));
				}
				$res[$name] = $type;
			}
		}
		ksort($res);
		return $res;
	}


	/**
	 * Calls all methods starting with with "inject" using autowiring.
	 * @param  object  $service
	 */
	public static function callInjects(DI\Container $container, $service): void
	{
		if (!is_object($service)) {
			throw new Nette\InvalidArgumentException(sprintf('Service must be object, %s given.', gettype($service)));
		}

		foreach (self::getInjectMethods(get_class($service)) as $method) {
			$container->callMethod([$service, $method]);
		}

		foreach (self::getInjectProperties(get_class($service)) as $property => $type) {
			self::checkType($service, $property, $type, $container);
			$service->$property = $container->getByType($type);
		}
	}


	/**
	 * @param  object|string  $class
	 * @param  DI\Container|DI\ContainerBuilder|null  $container
	 */
	private static function checkType($class, string $name, ?string $type, $container): void
	{
		$propName = Reflection::toString(new \ReflectionProperty($class, $name));
		if (!$type) {
			throw new Nette\InvalidStateException("Property $propName has no @var annotation.");
		} elseif (!class_exists($type) && !interface_exists($type)) {
			throw new Nette\InvalidStateException("Class or interface '$type' used in @var annotation at $propName not found. Check annotation and 'use' statements.");
		} elseif ($container && !$container->getByType($type, false)) {
			throw new Nette\DI\MissingServiceException("Service of type $type used in @var annotation at $propName not found. Did you register it in configuration file?");
		}
	}
}
