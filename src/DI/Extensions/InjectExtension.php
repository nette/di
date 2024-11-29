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

	#[\Deprecated('use InjectExtension::TagInject')]
	public const TAG_INJECT = self::TagInject;


	public function getConfigSchema(): Nette\Schema\Schema
	{
		return Nette\Schema\Expect::structure([]);
	}


	public function beforeCompile(): void
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
		$resolvedType = $def->getCreator()->resolveType(new DI\Resolver($this->getContainerBuilder()));
		$class = is_subclass_of($resolvedType, $def->getType())
			? $resolvedType
			: $def->getType();
		$setups = $def->getSetup();

		foreach (self::getInjectProperties($class) as $property => $type) {
			$builder = $this->getContainerBuilder();
			$inject = new Definitions\Statement(['@self', '$' . $property], [Definitions\Reference::fromType((string) $type)]);
			foreach ($setups as $key => $setup) {
				if ($setup->getEntity() == $inject->getEntity()) { // intentionally ==
					$inject = $setup;
					$builder = null;
					unset($setups[$key]);
				}
			}

			if ($builder) {
				self::checkType($class, $property, $type, $builder, $def);
			}
			array_unshift($setups, $inject);
		}

		foreach (array_reverse(self::getInjectMethods($class)) as $method) {
			$inject = new Definitions\Statement(['@self', $method]);
			foreach ($setups as $key => $setup) {
				if ($setup->getEntity() == $inject->getEntity()) { // intentionally ==
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
				? array_search($a, $methods, strict: true) <=> array_search($b, $methods, strict: true)
				: (is_a($classes[$a], $classes[$b], allow_string: true) ? 1 : -1));
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
			if (
				$rp->getAttributes(DI\Attributes\Inject::class)
				|| @DI\Helpers::parseAnnotation($rp, 'inject') !== null // @deprecated
			) {
				if (!$rp->getAttributes(DI\Attributes\Inject::class)) {
					trigger_error('Annotation @inject is deprecated, use #[Nette\\DI\\Attributes\\Inject] (used in ' . Reflection::toString($rp) . ')', E_USER_DEPRECATED);
				}
				if (!$rp->isPublic() || $rp->isStatic() || $rp->isReadOnly()) {
					throw new Nette\InvalidStateException(sprintf('Property %s for injection must not be static, readonly and must be public.', Reflection::toString($rp)));
				}

				$res[$rp->getName()] = DI\Helpers::ensureClassType(Nette\Utils\Type::fromReflection($rp), 'type of property ' . Reflection::toString($rp));
			}
		}

		ksort($res);
		return $res;
	}


	/**
	 * Calls all methods starting with "inject" using autowiring.
	 */
	public static function callInjects(DI\Container $container, object $service): void
	{
		foreach (self::getInjectMethods($service::class) as $method) {
			$container->callMethod([$service, $method]);
		}

		foreach (self::getInjectProperties($service::class) as $property => $type) {
			self::checkType($service, $property, $type, $container, null);
			$service->$property = $container->getByType($type);
		}
	}


	private static function checkType(
		object|string $class,
		string $name,
		?string $type,
		DI\Container|DI\ContainerBuilder $container,
		?Definitions\Definition $def,
	): void
	{
		if (!$container->getByType($type, throw: false)) {
			throw new Nette\DI\MissingServiceException(sprintf(
				"%sService of type %s required by %s not found.\nDid you add it to configuration file?",
				$def ? '[' . $def->getDescriptor() . "]\n" : '',
				$type,
				Reflection::toString(new \ReflectionProperty($class, $name)),
			));
		}
	}
}
