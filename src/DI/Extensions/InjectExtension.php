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
	public const TagInject = 'nette.inject';

	/** @deprecated use InjectExtension::TagInject */
	public const TAG_INJECT = self::TagInject;


	public function getConfigSchema(): Nette\Schema\Schema
	{
		return Nette\Schema\Expect::structure([]);
	}


	public function beforeCompile()
	{
		foreach ($this->getContainerBuilder()->getDefinitions() as $def) {
			if ($def->getTag(self::TagInject)) {
				$def = $def instanceof Definitions\FactoryDefinition
					? $def->getResultDefinition()
					: $def;
				if ($def instanceof Definitions\ServiceDefinition) {
					$this->updateDefinition($def);
				}
			}
		}
	}


	private function updateDefinition(Definitions\ServiceDefinition $def): void
	{
		$resolvedType = (new DI\Resolver($this->getContainerBuilder()))->resolveEntityType($def->getCreator());
		$class = is_subclass_of($resolvedType, $def->getType())
			? $resolvedType
			: $def->getType();
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

			self::checkType($class, $property, $type, $builder, $def);
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
		$classes = [];
		foreach (get_class_methods($class) as $name) {
			if (str_starts_with($name, 'inject')) {
				$classes[$name] = (new \ReflectionMethod($class, $name))->getDeclaringClass()->name;
			}
		}

		$methods = array_keys($classes);
		uksort($classes, fn(string $a, string $b): int => $classes[$a] === $classes[$b]
				? array_search($a, $methods, true) <=> array_search($b, $methods, true)
				: (is_a($classes[$a], $classes[$b], true) ? 1 : -1));
		return array_keys($classes);
	}


	/**
	 * Generates list of properties with annotation @inject.
	 * @internal
	 */
	public static function getInjectProperties(string $class): array
	{
		$res = [];
		foreach ((new \ReflectionClass($class))->getProperties() as $rp) {
			$name = $rp->getName();
			if (
				$rp->getAttributes(DI\Attributes\Inject::class)
				|| DI\Helpers::parseAnnotation($rp, 'inject') !== null
			) {
				if (!$rp->isPublic() || $rp->isStatic()) {
					trigger_error(sprintf('Property %s for injection must be public and non-static.', Reflection::toString($rp)), E_USER_WARNING);

				} elseif (PHP_VERSION_ID >= 80100 && $rp->isReadOnly()) {
					throw new Nette\InvalidStateException(sprintf('Property %s for injection must not be readonly.', Reflection::toString($rp)));

				} else {
					$res[$name] = DI\Helpers::ensureClassType(Nette\Utils\Type::fromReflection($rp), 'type of property ' . Reflection::toString($rp));
				}
			}
		}

		ksort($res);
		return $res;
	}


	/**
	 * Calls all methods starting with with "inject" using autowiring.
	 */
	public static function callInjects(DI\Container $container, object $service): void
	{
		foreach (self::getInjectMethods($service::class) as $method) {
			$container->callMethod([$service, $method]);
		}

		foreach (self::getInjectProperties($service::class) as $property => $type) {
			self::checkType($service, $property, $type, $container);
			$service->$property = $container->getByType($type);
		}
	}


	private static function checkType(
		object|string $class,
		string $name,
		?string $type,
		DI\Container|DI\ContainerBuilder|null $container,
		Definitions\Definition $def = null,
	): void
	{
		if ($container && !$container->getByType($type, throw: false)) {
			throw new Nette\DI\MissingServiceException(sprintf(
				"%sService of type %s required by %s not found.\nDid you add it to configuration file?",
				$def ? '[' . $def->getDescriptor() . "]\n" : '',
				$type,
				Reflection::toString(new \ReflectionProperty($class, $name)),
			));
		}
	}
}
