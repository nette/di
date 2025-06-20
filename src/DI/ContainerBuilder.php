<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI;

use Nette;
use Nette\DI\Definitions\Definition;
use function array_diff, array_filter, array_walk_recursive, class_implements, class_parents, is_a, is_int, key, ksort, preg_match, sprintf, strtolower;


/**
 * Container builder.
 */
class ContainerBuilder
{
	public const
		ThisService = 'self',
		ThisContainer = 'container';

	#[\Deprecated('use ContainerBuilder::ThisService')]
	public const THIS_SERVICE = self::ThisService;

	#[\Deprecated('use ContainerBuilder::ThisContainer')]
	public const THIS_CONTAINER = self::ThisContainer;

	public array $parameters = [];

	/** @var Definition[] */
	private array $definitions = [];
	private array $aliases = [];
	private Autowiring $autowiring;
	private bool $needsResolve = true;
	private bool $resolving = false;
	private array $dependencies = [];


	public function __construct()
	{
		$this->autowiring = new Autowiring($this);
		$this->addImportedDefinition(self::ThisContainer)->setType(Container::class);
	}


	/**
	 * Adds new service definition.
	 * @return Definitions\ServiceDefinition
	 */
	public function addDefinition(?string $name, ?Definition $definition = null): Definition
	{
		$this->needsResolve = true;
		if ($name === null) {
			for (
				$i = 1;
				isset($this->definitions['0' . $i]) || isset($this->aliases['0' . $i]);
				$i++
			);
			$name = '0' . $i; // prevents converting to integer in array key

		} elseif (is_int(key([$name => 1])) || !preg_match('#^\w+(\.\w+)*$#D', $name)) {
			throw new Nette\InvalidArgumentException(sprintf("Service name must be a alpha-numeric string and not a number, '%s' given.", $name));

		} else {
			$name = $this->aliases[$name] ?? $name;
			if (isset($this->definitions[$name])) {
				throw new Nette\InvalidStateException(sprintf("Service '%s' has already been added.", $name));
			}

			$lname = strtolower($name);
			foreach ($this->definitions as $nm => $foo) {
				if ($lname === strtolower($nm)) {
					throw new Nette\InvalidStateException(sprintf(
						"Service '%s' has the same name as '%s' in a case-insensitive manner.",
						$name,
						$nm,
					));
				}
			}
		}

		$definition = $definition ?: new Definitions\ServiceDefinition;
		$definition->setName($name);
		$definition->setNotifier(function (): void {
			$this->needsResolve = true;
		});
		return $this->definitions[$name] = $definition;
	}


	public function addAccessorDefinition(?string $name): Definitions\AccessorDefinition
	{
		return $this->addDefinition($name, new Definitions\AccessorDefinition);
	}


	public function addFactoryDefinition(?string $name): Definitions\FactoryDefinition
	{
		return $this->addDefinition($name, new Definitions\FactoryDefinition);
	}


	public function addLocatorDefinition(?string $name): Definitions\LocatorDefinition
	{
		return $this->addDefinition($name, new Definitions\LocatorDefinition);
	}


	public function addImportedDefinition(?string $name): Definitions\ImportedDefinition
	{
		return $this->addDefinition($name, new Definitions\ImportedDefinition);
	}


	/**
	 * Removes the specified service definition.
	 */
	public function removeDefinition(string $name): void
	{
		$this->needsResolve = true;
		$name = $this->aliases[$name] ?? $name;
		unset($this->definitions[$name]);
	}


	/**
	 * Gets the service definition.
	 */
	public function getDefinition(string $name): Definition
	{
		$service = $this->aliases[$name] ?? $name;
		if (!isset($this->definitions[$service])) {
			throw new MissingServiceException(sprintf("Service '%s' not found.", $name));
		}

		return $this->definitions[$service];
	}


	/**
	 * Gets all service definitions.
	 * @return Definition[]
	 */
	public function getDefinitions(): array
	{
		return $this->definitions;
	}


	/**
	 * Does the service definition or alias exist?
	 */
	public function hasDefinition(string $name): bool
	{
		$name = $this->aliases[$name] ?? $name;
		return isset($this->definitions[$name]);
	}


	public function addAlias(string $alias, string $service): void
	{
		if (!$alias) { // builder is not ready for falsy names such as '0'
			throw new Nette\InvalidArgumentException(sprintf("Alias name must be a non-empty string, '%s' given.", $alias));

		} elseif (!$service) { // builder is not ready for falsy names such as '0'
			throw new Nette\InvalidArgumentException(sprintf("Service name must be a non-empty string, '%s' given.", $service));

		} elseif (isset($this->aliases[$alias])) {
			throw new Nette\InvalidStateException(sprintf("Alias '%s' has already been added.", $alias));

		} elseif (isset($this->definitions[$alias])) {
			throw new Nette\InvalidStateException(sprintf("Service '%s' has already been added.", $alias));
		}

		$this->aliases[$alias] = $service;
	}


	/**
	 * Removes the specified alias.
	 */
	public function removeAlias(string $alias): void
	{
		unset($this->aliases[$alias]);
	}


	/**
	 * Gets all service aliases.
	 */
	public function getAliases(): array
	{
		return $this->aliases;
	}


	/**
	 * @param  string[]  $types
	 */
	public function addExcludedClasses(array $types): static
	{
		$this->needsResolve = true;
		$this->autowiring->addExcludedClasses($types);
		return $this;
	}


	/**
	 * Resolves autowired service name by type.
	 * @return ($throw is true ? string : ?string)
	 * @throws MissingServiceException
	 */
	public function getByType(string $type, bool $throw = false): ?string
	{
		return $this->getByTypeAndTag($type, null, $throw);
	}


	/**
	 * Resolves autowired service name by type and tag.
	 * @return ($throw is true ? string : ?string)
	 * @throws MissingServiceException
	 */
	public function getByTypeAndTag(string $type, ?string $tag = null, bool $throw = false): ?string
	{
		$this->needResolved();
		return $this->autowiring->getByTypeAndTag($type, $tag, $throw);
	}


	/**
	 * Gets autowired service definition of the specified type.
	 * @throws MissingServiceException
	 */
	public function getDefinitionByType(string $type): Definition
	{
		return $this->getDefinition($this->getByType($type, throw: true));
	}


	/**
	 * Gets the autowired service names and definitions of the specified type.
	 * @return Definition[]  service name is key
	 * @internal
	 */
	public function findAutowired(string $type): array
	{
		$this->needResolved();
		return $this->autowiring->findByType($type);
	}


	/**
	 * Gets the service names and definitions of the specified type.
	 * @return Definition[]  service name is key
	 */
	public function findByType(string $type): array
	{
		$this->needResolved();
		$found = [];
		foreach ($this->definitions as $name => $def) {
			if (is_a($def->getType(), $type, allow_string: true)) {
				$found[$name] = $def;
			}
		}

		return $found;
	}


	/**
	 * Gets the service names and tag values.
	 * @return array of [service name => tag attributes]
	 */
	public function findByTag(string $tag): array
	{
		$found = [];
		foreach ($this->definitions as $name => $def) {
			if (($tmp = $def->getTag($tag)) !== null) {
				$found[$name] = $tmp;
			}
		}

		return $found;
	}


	/********************* building ****************d*g**/


	/**
	 * Checks services, resolves types and rebuilts autowiring classlist.
	 */
	public function resolve(): void
	{
		if ($this->resolving) {
			return;
		}

		$this->resolving = true;

		$resolver = new Resolver($this);
		foreach ($this->definitions as $def) {
			$resolver->resolveDefinition($def);
		}

		$this->autowiring->rebuild();

		$this->resolving = $this->needsResolve = false;
	}


	private function needResolved(): void
	{
		if ($this->resolving) {
			throw new NotAllowedDuringResolvingException;
		} elseif ($this->needsResolve) {
			$this->resolve();
		}
	}


	public function complete(): void
	{
		$this->resolve();
		foreach ($this->definitions as $def) {
			$def->setNotifier(null);
		}

		$resolver = new Resolver($this);
		foreach ($this->definitions as $def) {
			$resolver->completeDefinition($def);
		}
	}


	/**
	 * Adds item to the list of dependencies.
	 * @internal
	 */
	public function addDependency(\ReflectionClass|\ReflectionFunctionAbstract|string $dep): static
	{
		$this->dependencies[] = $dep;
		return $this;
	}


	/**
	 * Returns the list of dependencies.
	 */
	public function getDependencies(): array
	{
		return $this->dependencies;
	}


	/** @internal */
	public function exportMeta(): array
	{
		$defs = $this->definitions;
		ksort($defs);
		foreach ($defs as $name => $def) {
			foreach ($def->getTags() as $tag => $value) {
				$meta['tags'][$tag][$name] = $value;
			}
		}

		$meta['aliases'] = $this->aliases;
		ksort($meta['aliases']);

		$all = [];
		foreach ($this->definitions as $name => $def) {
			if ($type = $def->getType()) {
				foreach (class_parents($type) + class_implements($type) + [$type] as $class) {
					$all[$class][] = $name;
				}
			}
		}

		[$low, $high] = $this->autowiring->getClassList();
		foreach ($all as $class => $names) {
			$meta['wiring'][$class] = array_filter([
				$high[$class] ?? [],
				$low[$class] ?? [],
				array_diff($names, $low[$class] ?? [], $high[$class] ?? []),
			]);
		}

		return $meta;
	}


	public static function literal(string $code, ?array $args = null): Nette\PhpGenerator\Literal
	{
		return new Nette\PhpGenerator\Literal(
			$args === null ? $code : (new Nette\PhpGenerator\Dumper)->format($code, ...$args),
		);
	}


	/** @deprecated */
	public function formatPhp(string $statement, array $args): string
	{
		array_walk_recursive($args, function (&$val): void {
			if ($val instanceof Nette\DI\Definitions\Expression) {
				$val->complete(new Resolver($this));

			} elseif ($val instanceof Definition) {
				$val = new Definitions\Reference($val->getName());
			}
		});
		return (new PhpGenerator($this))->formatPhp($statement, $args);
	}
}
