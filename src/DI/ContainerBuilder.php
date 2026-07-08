<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI;

use Nette;
use Nette\DI\Compiler\Autowiring;
use Nette\DI\Compiler\PhpGenerator;
use Nette\DI\Compiler\Resolver;
use function in_array, is_int, sprintf;


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

	/** @var array<string, mixed> */
	public array $parameters = [];

	/** @var array<string, Definition> */
	private array $definitions = [];

	/** @var array<string, string> lowercase name => service name */
	private array $lowerNames = [];

	/** @var array<string, string> alias => service name */
	private array $aliases = [];
	private Autowiring $autowiring;
	private bool $needsResolve = true;
	private bool $resolving = false;
	private ?Compiler\Schedule $schedule = null;

	/** @var list<\ReflectionClass<object>|\ReflectionFunctionAbstract|string> */
	private array $dependencies = [];


	public function __construct()
	{
		$this->autowiring = new Autowiring($this);
		$this->addImportedDefinition(self::ThisContainer)->setType(Container::class);
	}


	/**
	 * Connects the builder to the compilation schedule so that hook() can defer work into a phase.
	 * @internal  called by Compiler
	 */
	public function setSchedule(Compiler\Schedule $schedule): void
	{
		$this->schedule = $schedule;
	}


	/**
	 * Adds new service definition.
	 * @template TDef of Definition
	 * @param  TDef|null  $definition
	 * @return ($definition is null ? Definitions\ServiceDefinition : TDef)
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

		} elseif ($name === self::ThisService) {
			throw new Nette\InvalidArgumentException("Service name 'self' is reserved for references to the current service.");

		} else {
			$name = $this->aliases[$name] ?? $name;
			if (isset($this->definitions[$name])) {
				throw new Nette\InvalidStateException(sprintf("Service '%s' has already been added.", $name));
			}

			if ($similar = $this->lowerNames[strtolower($name)] ?? null) {
				throw new Nette\InvalidStateException("Service '$name' has the same name as '$similar' in a case-insensitive manner.");
			}
		}

		$definition ??= new Definitions\ServiceDefinition;
		$definition->setName($name);
		$definition->setNotifier(function (): void {
			$this->needsResolve = true;
		});
		$this->lowerNames[strtolower($name)] = $name;
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
	 * Registers a new service (errors if the name exists); returns its definition for fluent config.
	 * The creator is a class name, an expression or a ready-made definition.
	 * @template TDef of Definition
	 * @param  class-string|Expression|TDef  $creator
	 * @return ($creator is Definition ? TDef : Definitions\ServiceDefinition)
	 */
	public function add(?string $name, string|Expression|Definition $creator): Definition
	{
		if ($creator instanceof Definition) {
			return $this->addDefinition($name, $creator);
		}

		$def = new Definitions\ServiceDefinition;
		$def->setCreator($creator instanceof Expression ? $creator : create($creator));
		return $this->addDefinition($name, $def);
	}


	/**
	 * Removes the specified service definition.
	 */
	public function removeDefinition(string $name): void
	{
		$this->needsResolve = true;
		$name = $this->aliases[$name] ?? $name;
		unset($this->definitions[$name], $this->lowerNames[strtolower($name)]);
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
	 * @return array<string, Definition>
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


	public function removeAlias(string $alias): void
	{
		unset($this->aliases[$alias]);
	}


	/**
	 * Gets all service aliases.
	 * @return array<string, string>
	 */
	public function getAliases(): array
	{
		return $this->aliases;
	}


	/**
	 * Excludes classes from autowiring.
	 * @param  class-string[]  $types
	 */
	public function addExcludedClasses(array $types): static
	{
		$this->needsResolve = true;
		$this->autowiring->addExcludedClasses($types);
		return $this;
	}


	/**
	 * Resolves autowired service name by type.
	 * @param  class-string  $type
	 * @return ($throw is true ? string : ?string)
	 * @throws MissingServiceException
	 * @throws NotAllowedDuringResolvingException
	 */
	public function getByType(string $type, bool $throw = false): ?string
	{
		$this->needResolved();
		return $this->autowiring->getByType($type, $throw);
	}


	/**
	 * Gets autowired service definition of the specified type.
	 * @param  class-string  $type
	 * @throws MissingServiceException
	 */
	public function getDefinitionByType(string $type): Definition
	{
		return $this->getDefinition($this->getByType($type, throw: true));
	}


	/**
	 * Gets the autowired service names and definitions of the specified type.
	 * @param  class-string  $type
	 * @return array<string, Definition>
	 * @throws NotAllowedDuringResolvingException
	 * @internal
	 */
	public function findAutowired(string $type): array
	{
		$this->needResolved();
		return $this->autowiring->findByType($type);
	}


	/**
	 * Gets the service names and definitions of the specified type.
	 * @param  class-string  $type
	 * @return array<string, Definition>
	 * @throws NotAllowedDuringResolvingException
	 */
	public function findByType(string $type): array
	{
		$this->needResolved();
		$found = [];
		foreach ($this->definitions as $name => $def) {
			if ($def->getType() !== null && is_a($def->getType(), $type, allow_string: true)) {
				$found[$name] = $def;
			}
		}

		return $found;
	}


	/**
	 * Gets the service names and tag values.
	 * @return array<string, mixed>  service name => tag value
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


	/********************* retrieval (config-facing) ****************d*g**/


	/**
	 * Gets a single service definition by name or by type, asserting it is of the given definition
	 * class (ServiceDefinition by default - the common case). Pass exactly one of name/type;
	 * addressing by type throws when zero or several services match.
	 * @template T of Definition
	 * @param  class-string|null  $type
	 * @param  class-string<T>|null  $of
	 * @return ($of is null ? Definitions\ServiceDefinition : T)
	 */
	public function get(?string $name = null, ?string $type = null, ?string $of = null): Definition
	{
		try {
			$def = match (true) {
				$type !== null && $name === null => $this->getDefinitionByType($type),
				$name !== null && $type === null => $this->getDefinition($name),
				default => throw new Nette\InvalidArgumentException('Pass exactly one of name or type to get().'),
			};
		} catch (MissingServiceException $e) {
			throw new MissingServiceException($e->getMessage() . $this->timelineHint());
		}

		$of ??= Definitions\ServiceDefinition::class;
		if (!$def instanceof $of) {
			throw new MissingServiceException(sprintf(
				"Service '%s' is a %s, not the expected %s; pass of: to get() to address it.",
				$def->getName(),
				$def::class,
				$of,
			));
		}

		return $def;
	}


	/**
	 * Removes a service by name or type; the definition must exist (immediate, no deferral).
	 * To drop a service registered by an extension, use hook(Phase::Discover, ..., after: '*').
	 * @param  class-string|null  $type
	 */
	public function remove(?string $name = null, ?string $type = null): void
	{
		$def = $this->get($name, $type, of: Definition::class);
		assert($def->getName() !== null);
		$this->removeDefinition($def->getName());
	}


	/**
	 * A hint appended to a "service not found" error while services may still be registered
	 * (before the Discover phase completes), teaching the config-time vs. hook() timeline.
	 */
	private function timelineHint(): string
	{
		return $this->schedule && !$this->schedule->isCompleted(Phase::Discover)
			? ' Services registered by extensions are not available yet at config time; reach them from a hook(Phase::Modify, ...).'
			: '';
	}


	/**
	 * Gets all service definitions matching a type or a tag, optionally filtered to a definition
	 * class via of:. Unlike findByTag(), find(tag:) returns the definitions, not the tag values.
	 * @template T of Definition
	 * @param  class-string|null  $type
	 * @param  class-string<T>|null  $of
	 * @return ($of is null ? array<string, Definition> : array<string, T>)
	 */
	public function find(?string $type = null, ?string $tag = null, ?string $of = null): array
	{
		$found = match (true) {
			$type !== null && $tag === null => $this->findByType($type),
			$tag !== null && $type === null => $this->getDefinitionsByTag($tag),
			default => throw new Nette\InvalidArgumentException('Pass exactly one of type or tag to find().'),
		};

		return $of === null
			? $found
			: array_filter($found, fn(Definition $def): bool => $def instanceof $of);
	}


	/**
	 * @return array<string, Definition>
	 */
	private function getDefinitionsByTag(string $tag): array
	{
		$found = [];
		foreach (array_keys($this->findByTag($tag)) as $name) {
			$found[$name] = $this->getDefinition($name);
		}

		return $found;
	}


	/**
	 * Does a service of the given name or type exist? Pass exactly one.
	 * @param  class-string|null  $type
	 */
	public function has(?string $name = null, ?string $type = null): bool
	{
		if ($type !== null && $name === null) {
			return (bool) $this->findByType($type);
		} elseif ($name !== null && $type === null) {
			return $this->hasDefinition($name);
		}

		throw new Nette\InvalidArgumentException('Pass exactly one of name or type to has().');
	}


	/**
	 * Gets all service definitions (alias of getDefinitions()).
	 * @return array<string, Definition>
	 */
	public function getAll(): array
	{
		return $this->definitions;
	}


	/**
	 * Defers a callback into a compilation phase; the callback receives this builder. This is the
	 * only way to reach services that other extensions register later - the rest of the vocabulary
	 * is immediate. Only the Register, Discover and Modify phases are available here.
	 * @param  string|string[]|null  $before  extension(s) before which the hook should run
	 * @param  string|string[]|null  $after   extension(s) after which the hook should run
	 */
	public function hook(
		Phase $phase,
		\Closure $callback,
		string|array|null $before = null,
		string|array|null $after = null,
	): void
	{
		if (!in_array($phase, [Phase::Register, Phase::Discover, Phase::Modify], strict: true)) {
			throw new Nette\InvalidArgumentException(sprintf(
				'hook() supports only the Register, Discover and Modify phases, %s given.',
				$phase->name,
			));
		} elseif (!$this->schedule) {
			throw new Nette\InvalidStateException(
				'Scheduling a hook requires compilation via Nette\DI\Compiler; a standalone ContainerBuilder cannot run phases.',
			);
		}

		$this->schedule->add($phase, $callback, before: $before, after: $after);
	}


	/********************* building ****************d*g**/


	/**
	 * Resolves service types and rebuilds the autowiring class list.
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


	/**
	 * Completes all service definitions by resolving and wiring arguments.
	 */
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
	 * @param  \ReflectionClass<object>|\ReflectionFunctionAbstract|string  $dep
	 * @internal
	 */
	public function addDependency(\ReflectionClass|\ReflectionFunctionAbstract|string $dep): static
	{
		$this->dependencies[] = $dep;
		return $this;
	}


	/**
	 * Returns the list of dependencies.
	 * @return list<\ReflectionClass<object>|\ReflectionFunctionAbstract|string>
	 */
	public function getDependencies(): array
	{
		return $this->dependencies;
	}


	/**
	 * @return array{tags?: array<string, array<string, mixed>>, aliases: array<string, string>, wiring: array<class-string, array<int, list<string>>>}
	 * @internal
	 */
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


	/**
	 * Creates a PHP literal value, optionally formatted with arguments.
	 * @param  array<mixed>|null  $args
	 */
	public static function literal(string $code, ?array $args = null): Nette\PhpGenerator\Literal
	{
		return new Nette\PhpGenerator\Literal(
			$args === null ? $code : (new Nette\PhpGenerator\Dumper)->format($code, ...$args),
		);
	}


	/**
	 * @param  array<mixed>  $args
	 * @deprecated
	 */
	public function formatPhp(string $statement, array $args): string
	{
		array_walk_recursive($args, function (&$val): void {
			if ($val instanceof Expression) {
				$val = $val->complete(new Resolver($this));

			} elseif ($val instanceof Definition) {
				assert($val->getName() !== null);
				$val = new Expressions\Reference($val->getName());
			}
		});
		return (new PhpGenerator($this))->formatPhp($statement, $args);
	}
}
