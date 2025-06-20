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
use function array_keys, array_reverse, array_search, array_unshift, get_class_methods, is_a, is_subclass_of, ksort, sprintf, str_starts_with, uksort;


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

		// Inject attributes in constructor parameters
		$constructor = (new \ReflectionClass($class))->getConstructor();
		if ($constructor !== null) {
			foreach ($constructor->getParameters() as $param) {
				$attributes = $param->getAttributes(DI\Attributes\Inject::class);
				if ($attributes !== []) {
					$injectAttribute = $attributes[0]->newInstance();
					$tag = $injectAttribute->tag;
					if ($tag === null) {
						throw new Nette\InvalidStateException(sprintf(
							'Attribute #[Inject] on parameter $%s in %s is redundant.',
							$param->getName(),
							Reflection::toString($constructor),
						));
					}

					$type = Nette\Utils\Type::fromReflection($param);
					if ($type === null) {
						throw new Nette\InvalidStateException(sprintf(
							'Parameter $%s in %s has no type hint.',
							$param->getName(),
							Reflection::toString($constructor),
						));
					}

					// Update the creator arguments to use the tagged service
					$creator = $def->getCreator();
					$arguments = $creator->arguments;
					$arguments[$param->getName()] = Definitions\Reference::fromType((string) $type, $tag);
					$def->setCreator($creator->getEntity(), $arguments);
				}
			}
		}

		// Inject attributes in properties
		foreach (self::getInjectProperties($class) as $property => $typeAndTag) {
			$type = $typeAndTag['type'];
			$tag = $typeAndTag['tag'];

			$builder = $this->getContainerBuilder();
			$inject = new Definitions\Statement(
				['@self', '$' . $property],
				[Definitions\Reference::fromType($type, $tag)],
			);

			foreach ($setups as $key => $setup) {
				if ($setup->getEntity() == $inject->getEntity()) { // intentionally ==
					$inject = $setup;
					$builder = null;
					unset($setups[$key]);
				}
			}

			if ($builder !== null) {
				self::checkType($class, $property, $type, $builder, $tag);
			}
			array_unshift($setups, $inject);
		}

		foreach (array_reverse(self::getInjectMethods($class)) as $method) {
			$inject = new Definitions\Statement(['@self', $method]);
			$methodReflection = new \ReflectionMethod($class, $method);
			$arguments = [];

			// Inject attributes in inject methods
			foreach ($methodReflection->getParameters() as $param) {
				$attributes = $param->getAttributes(DI\Attributes\Inject::class);
				if ($attributes !== []) {
					$injectAttribute = $attributes[0]->newInstance();
					$tag = $injectAttribute->tag;
					if ($tag === null) {
						throw new Nette\InvalidStateException(sprintf(
							'Parameter %s has #[Inject] attribute, but no tag specified.',
							Reflection::toString($param),
						));
					}

					$type = Nette\Utils\Type::fromReflection($param);
					if ($type === null) {
						throw new Nette\InvalidStateException(sprintf(
							'Parameter $%s in %s has no type hint.',
							$param->getName(),
							Reflection::toString($methodReflection),
						));
					}
					$arguments[$param->getName()] = Definitions\Reference::fromType((string) $type, $tag);
				}
			}

			if ($arguments !== []) {
				$inject = new Definitions\Statement(['@self', $method], $arguments);
			}

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
	 * @return array<string, array{type: string, tag: string|null}>
	 */
	public static function getInjectProperties(string $class): array
	{
		$res = [];
		foreach ((new \ReflectionClass($class))->getProperties() as $rp) {
			if ($rp->isPromoted()) {
				continue; // Setup is in constructor
			}

			$hasAttr = $rp->getAttributes(DI\Attributes\Inject::class);
			if ($hasAttr || DI\Helpers::parseAnnotation($rp, 'inject') !== null) {
				if (!$rp->isPublic() || $rp->isStatic() || $rp->isReadOnly()) {
					throw new Nette\InvalidStateException(sprintf('Property %s for injection must not be static, readonly and must be public.', Reflection::toString($rp)));
				}

				$type = Nette\Utils\Type::fromReflection($rp);
				if (!$type && !$hasAttr && ($annotation = DI\Helpers::parseAnnotation($rp, 'var'))) {
					$annotation = Reflection::expandClassName($annotation, Reflection::getPropertyDeclaringClass($rp));
					$type = Nette\Utils\Type::fromString($annotation);
				}

				$tag = null;
				if ($hasAttr !== []) {
					$tag = $hasAttr[0]->newInstance()->tag;
				}

				$res[$rp->getName()] = ['type' => DI\Helpers::ensureClassType($type, 'type of property ' . Reflection::toString($rp)), 'tag' => $tag];
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

		foreach (self::getInjectProperties($service::class) as $property => $propertyInfo) {
			$type = $propertyInfo['type'];
			$tag = $propertyInfo['tag'];
			self::checkType($service, $property, $type, $container, $tag);
			$service->$property = $container->getByTypeAndTag($type, $tag, throw: true);
		}
	}


	private static function checkType(
		object|string $class,
		string $name,
		?string $type,
		DI\Container|DI\ContainerBuilder $container,
		?string $tag = null,
	): void
	{
		if (!$container->getByTypeAndTag($type, $tag, throw: false)) {
			throw new Nette\DI\MissingServiceException(sprintf(
				'Service of type %s%s required by %s not found. Did you add it to configuration file?',
				$type,
				$tag !== null ? " with tag '$tag'" : '',
				Reflection::toString(new \ReflectionProperty($class, $name)),
			));
		}
	}
}
