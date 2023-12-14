<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Extensions;

use Nette;
use Nette\DI;
use Nette\DI\Attributes\Hook;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions;
use Nette\DI\Phase;
use Nette\Utils\Reflection;
use function sprintf;


/**
 * Calls inject methods and fills #[Inject] properties.
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


	#[Hook(Phase::Modify, after: '*')]
	public function doProcessInjectAttributes(ContainerBuilder $builder): void
	{
		foreach ($builder->getAll() as $def) {
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
		$resolvedType = $def->getCreator()->resolveType(new DI\Compiler\Resolver($this->getContainerBuilder()));
		$class = $resolvedType && $def->getType() && is_subclass_of($resolvedType, $def->getType())
			? $resolvedType
			: $def->getType();
		if (!$class) {
			return;
		}

		$setups = $def->getSetup();

		foreach (self::getInjectProperties($class) as $property => $type) {
			$builder = $this->getContainerBuilder();
			$inject = new DI\Expressions\PropertyAccess(
				di\self(),
				$property,
				DI\Expressions\PropertyMode::Assign,
				di\wire((string) $type),
			);
			foreach ($setups as $key => $setup) {
				if ($setup->getEntity() == $inject->getEntity()) { // intentionally ==
					$inject = $setup;
					$builder = null;
					unset($setups[$key]);
				}
			}

			if ($builder) {
				self::checkType($class, $property, $type, $builder);
			}
			array_unshift($setups, $inject);
		}

		foreach (array_reverse(self::getInjectMethods($class)) as $method) {
			$inject = di\self()->method($method);
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
	 * Returns list of inject method names, ordered from parent to child class.
	 * @return string[]
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
	 * Returns list of injectable properties with their types.
	 * @return array<string, class-string>
	 * @internal
	 */
	public static function getInjectProperties(string $class): array
	{
		$res = [];
		foreach ((new \ReflectionClass($class))->getProperties() as $rp) {
			if ($rp->getAttributes(DI\Attributes\Inject::class)) {
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
	 * Calls inject methods and fills inject properties on the given service.
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
		DI\Container|DI\ContainerBuilder $container,
	): void
	{
		if (!$container->getByType($type, throw: false)) {
			throw new Nette\DI\MissingServiceException(sprintf(
				'Service of type %s required by %s not found. Did you add it to configuration file?',
				$type,
				Reflection::toString(new \ReflectionProperty($class, $name)),
			));
		}
	}
}
