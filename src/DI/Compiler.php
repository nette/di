<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI;

use Nette;
use Nette\DI\Compiler\{DependencyChecker, PhpGenerator};
use Nette\Schema;
use function count, in_array, is_array, sprintf;


/**
 * DI container compiler.
 */
class Compiler
{
	/** internal config section holding PHP config-file closures operating on Definitions */
	public const Closures = '@closures';

	private const
		Services = 'services',
		Parameters = 'parameters',
		DI = 'di';

	/** @var CompilerExtension[] */
	private array $extensions = [];

	/** @var array<string, mixed> */
	private array $config = [];

	/** @var array<string, array<mixed[]>> [section => array[]] */
	private array $configs = [];
	private string $sources = '';
	private DependencyChecker $dependencies;
	private string $className = 'Container';
	private readonly Compiler\Schedule $schedule;

	private ?Nette\PhpGenerator\ClassType $class = null;

	/** @var array<string, true> extension names that have been processed */
	private array $processedExtensions = [];


	public function __construct(
		private readonly ContainerBuilder $builder = new ContainerBuilder,
	) {
		$this->dependencies = new DependencyChecker;
		$this->schedule = new Compiler\Schedule;
		$this->builder->setSchedule($this->schedule);
		$this->addExtension(self::Services, new Extensions\ServicesExtension);
		$this->addExtension(self::Parameters, new Extensions\ParametersExtension($this->configs));
	}


	/**
	 * Adds a compiler extension. Pass null as name to auto-assign a name.
	 */
	public function addExtension(?string $name, CompilerExtension $extension): static
	{
		if ($name === null) {
			$name = '_' . count($this->extensions);
		} elseif (isset($this->extensions[$name])) {
			throw new Nette\InvalidArgumentException(sprintf("Name '%s' is already used or reserved.", $name));
		}

		$lname = strtolower($name);
		foreach (array_keys($this->extensions) as $nm) {
			if ($lname === strtolower((string) $nm)) {
				throw new Nette\InvalidArgumentException(sprintf(
					"Name of extension '%s' has the same name as '%s' in a case-insensitive manner.",
					$name,
					$nm,
				));
			}
		}

		$this->extensions[$name] = $extension->setCompiler($this, $name);
		return $this;
	}


	/**
	 * Returns all registered extensions, optionally filtered by class or interface.
	 * @template T of object
	 * @param  class-string<T>|null  $type
	 * @return ($type is null ? array<string, CompilerExtension> : array<string, CompilerExtension&T>)
	 */
	public function getExtensions(?string $type = null): array
	{
		return $type
			? array_filter($this->extensions, fn($item): bool => $item instanceof $type)
			: $this->extensions;
	}


	/**
	 * Registers a hook for given phase. Hooks run in registration order unless before/after constraints dictate otherwise.
	 * @param  string|string[]|null  $before
	 * @param  string|string[]|null  $after
	 * @internal
	 */
	public function addHook(
		Phase $phase,
		\Closure $callback,
		?CompilerExtension $extension = null,
		string|array|null $before = null,
		string|array|null $after = null,
	): void
	{
		$this->schedule->add($phase, $callback, $extension, $before, $after);
	}


	public function getContainerBuilder(): ContainerBuilder
	{
		return $this->builder;
	}


	/**
	 * Sets the class name of the generated container.
	 */
	public function setClassName(string $className): static
	{
		$this->className = $className;
		return $this;
	}


	/**
	 * Adds new configuration. Once the parameters are expanded, the configuration is expanded on adding.
	 * @param  array<string, mixed>  $config
	 */
	public function addConfig(array $config): static
	{
		if (isset($this->processedExtensions[self::Parameters])) {
			if (isset($config[self::Parameters])) {
				throw new Nette\InvalidStateException("Section 'parameters' cannot be added once the parameters are expanded.");
			}

			$config = Helpers::expand($config, $this->builder->parameters);
		}

		foreach ($config as $section => $data) {
			$this->configs[$section][] = $data;
		}

		$this->sources .= "// source: array\n";
		return $this;
	}


	/**
	 * Adds new configuration from file.
	 */
	public function loadConfig(string $file, ?Config\Loader $loader = null): static
	{
		$sources = $this->sources . "// source: $file\n";
		$loader ??= new Config\Loader;
		foreach ($loader->load($file, merge: false) as $data) {
			$this->addConfig($data);
		}

		$this->dependencies->add($loader->getDependencies());
		$this->sources = $sources;
		return $this;
	}


	/**
	 * Returns configuration.
	 * @return array<string, mixed>
	 * @deprecated
	 */
	public function getConfig(): array
	{
		return $this->config;
	}


	/**
	 * Sets the names of dynamic parameters. A name given as a list of keys addresses
	 * a value nested inside a parameter (its runtime name is the dot-joined path).
	 * @param  array<string|list<string>>  $names
	 */
	public function setDynamicParameterNames(array $names): static
	{
		assert($this->extensions[self::Parameters] instanceof Extensions\ParametersExtension);
		$this->extensions[self::Parameters]->dynamicParams = $names;
		return $this;
	}


	/**
	 * Adds dependencies to the list.
	 * @param array<\ReflectionClass<object>|\ReflectionFunctionAbstract|string>  $deps
	 */
	public function addDependencies(array $deps): static
	{
		$this->dependencies->add(array_filter($deps));
		return $this;
	}


	/**
	 * Exports dependencies.
	 * @return array{int, array<string, int|false>, array<string, int|false>, string[], string[], string}
	 */
	public function exportDependencies(): array
	{
		return $this->dependencies->export();
	}


	/**
	 * Adds a tag to export from the container.
	 */
	public function addExportedTag(string $tag): static
	{
		if (isset($this->extensions[self::DI])) {
			assert($this->extensions[self::DI] instanceof Extensions\DIExtension);
			$this->extensions[self::DI]->exportedTags[$tag] = true;
		}

		return $this;
	}


	/**
	 * Adds a type to export from the container.
	 * @param  class-string  $type
	 */
	public function addExportedType(string $type): static
	{
		if (isset($this->extensions[self::DI])) {
			assert($this->extensions[self::DI] instanceof Extensions\DIExtension);
			$this->extensions[self::DI]->exportedTypes[$type] = true;
		}

		return $this;
	}


	/**
	 * Compiles the container and returns the generated PHP code.
	 */
	public function compile(): string
	{
		$this->processedExtensions = [];
		$this->schedule->clear();
		$this->runConfigClosures();
		$this->processExtensions();

		foreach ([Phase::Discover, Phase::Modify] as $phase) {
			$this->runPhase($phase);
		}

		$this->builder->complete();
		return $this->generateCode();
	}


	/**
	 * Processes extensions: validates config, calls registerHooks() and runs the Setup
	 * and Register phases. Extensions are ordered by their Setup hooks; ServicesExtension
	 * goes last, once all definitions are visible. New extensions may be added during Setup,
	 * hence the loop.
	 * @internal
	 */
	public function processExtensions(): void
	{
		while ($unprocessed = array_diff_key($this->extensions, $this->processedExtensions, [self::Services => 1])) {
			if ($this->processedExtensions) {
				$this->checkLateSetupConstraints($unprocessed);
			}

			foreach ($this->sortExtensionsBySetupHooks($unprocessed) as $name) {
				$this->initExtension($name);
				$this->runPhaseForExtension(Phase::Setup, $this->extensions[$name]);
			}
		}

		$this->schedule->markCompleted(Phase::Setup);
		$this->runPhase(Phase::Register, final: false); // ServicesExtension's Register hook runs in the second drain

		if ($extra = array_diff_key($this->extensions, $this->processedExtensions, [self::Services => 1])) {
			throw new Nette\DeprecatedException(sprintf(
				"Extensions '%s' were added while container was being compiled.",
				implode("', '", array_keys($extra)),
			));
		}

		if (isset($this->extensions[self::Services])) {
			$this->initExtension(self::Services);
		}

		foreach ($this->extensions as $extension) {
			$this->dependencies->add(array_filter([new \ReflectionClass($extension)->getFileName()]));
		}

		if ($extra = key(array_diff_key($this->configs, $this->extensions, [self::Closures => 1]))) {
			$hint = Nette\Utils\Helpers::getSuggestion(array_keys($this->extensions), $extra);
			throw new InvalidConfigurationException(
				sprintf("Found section '%s' in configuration, but corresponding extension is missing", $extra)
				. ($hint ? ", did you mean '$hint'?" : '.'),
			);
		}

		// Register runs again for ServicesExtension's hook, registered only above
		$this->runPhase(Phase::Register);
	}


	/**
	 * Runs the closures returned by PHP config files (see PhpAdapter) against the Definitions.
	 * They run first, at load-time: their add() is immediate and precedes the extensions, while
	 * hook()/remove() reaching framework services must be deferred into a phase (ADR definitions-dsl).
	 */
	private function runConfigClosures(): void
	{
		$this->schedule->setCurrentActor('config');
		foreach ($this->configs[self::Closures] ?? [] as $closures) {
			foreach ($closures as $closure) {
				$closure($this->builder);
			}
		}

		$this->schedule->setCurrentActor(null);
	}


	/**
	 * Validates extension config and calls its registerHooks().
	 */
	private function initExtension(string $name): void
	{
		$extension = $this->extensions[$name];
		$config = $this->processSchema(
			$extension->getConfigSchema(),
			$this->configs[$name] ?? [],
			$name,
			services: $extension instanceof Extensions\ServicesExtension,
		);
		$extension->setConfig($this->config[$name] = $config);
		$extension->registerHooks();
		$this->processedExtensions[$name] = true;
	}


	/**
	 * An extension registered during the Setup phase (e.g. via the extensions: section) cannot
	 * jump before extensions whose Setup hooks have already run; fail loudly instead of silently
	 * violating the constraint.
	 * @param  array<string, CompilerExtension>  $extensions
	 */
	private function checkLateSetupConstraints(array $extensions): void
	{
		foreach ($extensions as $name => $extension) {
			foreach ($this->getSetupConstraints($extension)['before'] as $target) {
				$ranBefore = $target === '*'
					|| array_filter(
						array_intersect_key($this->extensions, $this->processedExtensions),
						fn($processed) => is_a($processed, $target),
					);
				if ($ranBefore) {
					throw new Nette\InvalidStateException(sprintf(
						"Extension '%s' declares a Setup hook with before: %s, but it was registered too late - the targeted Setup hooks have already run. Register the extension directly instead of dynamically.",
						$name,
						$target === '*' ? "'*'" : $target,
					));
				}
			}
		}
	}


	/**
	 * Orders extensions by the before/after constraints of their Setup hooks.
	 * @param  array<string, CompilerExtension>  $extensions
	 * @return string[]
	 */
	private function sortExtensionsBySetupHooks(array $extensions): array
	{
		$items = [];
		foreach ($extensions as $name => $extension) {
			$items[$name] = ['owner' => $extension] + $this->getSetupConstraints($extension);
		}

		return Helpers::sortByConstraints($items);
	}


	/**
	 * Collects before/after constraints from all Setup hooks declared via attributes.
	 * @return array{before: string[], after: string[]}
	 */
	private function getSetupConstraints(CompilerExtension $extension): array
	{
		$before = $after = [];
		foreach (new \ReflectionClass($extension)->getMethods() as $method) {
			foreach ($method->getAttributes(Attributes\Hook::class) as $attr) {
				$hook = $attr->newInstance();
				if ($hook->phase === Phase::Setup) {
					$before = [...$before, ...(array) ($hook->before ?? [])];
					$after = [...$after, ...(array) ($hook->after ?? [])];
				}
			}
		}

		return ['before' => $before, 'after' => $after];
	}


	/**
	 * Runs all hooks registered for given phase, in constraint order.
	 */
	private function runPhase(Phase $phase, bool $final = true): void
	{
		if ($phase === Phase::Modify) {
			$this->builder->resolve(); // so hooks see resolved types
		}

		$this->schedule->markRunning($phase);
		foreach ($this->schedule->drainSorted($phase) as $hook) {
			$this->schedule->setCurrentActor($hook['extension']?->getName());
			($hook['callback'])($phase === Phase::Compile ? $this->class : $this->builder);
			if ($phase === Phase::Modify) {
				$this->builder->resolve(); // re-resolve for definitions added by the hook
			}
		}

		$this->schedule->setCurrentActor(null);
		$this->schedule->markIdle();
		if ($final) {
			$this->schedule->markCompleted($phase);
		}
	}


	/**
	 * Runs the phase hooks belonging to a single extension instance, leaving the rest untouched.
	 */
	private function runPhaseForExtension(Phase $phase, CompilerExtension $extension): void
	{
		$this->schedule->markRunning($phase);
		$this->schedule->setCurrentActor($extension->getName());
		foreach ($this->schedule->drainForExtension($phase, $extension) as $hook) {
			($hook['callback'])($this->builder);
		}

		$this->schedule->setCurrentActor(null);
		$this->schedule->markIdle();
	}


	/**
	 * Merges and validates configurations against scheme.
	 * @param  array<mixed[]>  $configs
	 * @return array<string, mixed>|object
	 */
	private function processSchema(
		Schema\Schema $schema,
		array $configs,
		?string $name = null,
		bool $services = false,
	): array|object
	{
		$configs = self::resolveMergingMarkers(array_values($configs), $services);
		$processor = new Schema\Processor;
		$processor->onNewContext[] = function (Schema\Context $context) use ($name) {
			$context->path = $name ? [$name] : [];
			$context->dynamics = &$this->extensions[self::Parameters]->dynamicValidators;
		};
		try {
			$res = $processor->processMultiple($schema, $configs);
		} catch (Schema\ValidationException $e) {
			throw new Nette\DI\InvalidConfigurationException($e->getMessage());
		}

		foreach ($processor->getWarnings() as $warning) {
			trigger_error($warning, E_USER_DEPRECATED);
		}

		return $res;
	}


	/**
	 * Resolves '!' merging markers: a marked key discards its value from earlier configuration layers.
	 * Inside service definitions the marker also translates to the 'reset' option.
	 * @param  list<mixed>  $configs
	 * @return list<mixed>
	 */
	private static function resolveMergingMarkers(array $configs, bool $services): array
	{
		$resets = []; // resets of services whose definition is not an array, so it cannot carry them
		foreach ($configs as $i => $config) {
			/** @var list<list<int|string>> $paths */
			$paths = [];
			if ($config instanceof Config\Replacement) {
				$config = $config->value;
				$paths[] = [];
			} elseif (is_array($config) && isset($config[Config\Helpers::PREVENT_MERGING])) {
				unset($config[Config\Helpers::PREVENT_MERGING]);
				$paths[] = [];
			}

			if (is_array($config)) { // empty section is null
				$config = self::stripMergingMarkers($config, [], $paths);
			}

			if ($services) {
				foreach ($paths as $path) {
					if (count($path) === 1) {
						if (is_array($config[$path[0]])) {
							$config[$path[0]]['reset']['all'] = true;
						} else {
							$resets[$path[0]]['reset']['all'] = true;
						}
					} elseif (count($path) === 2 && in_array($path[1], ['arguments', 'setup', 'tags'], true)) {
						$config[$path[0]]['reset'][$path[1]] = true;
					}
				}
			}

			$configs[$i] = $config;
			foreach ($paths as $path) {
				if (array_filter($path, is_int(...))) {
					continue; // items under a numeric key are appended, an earlier layer holds a different item there
				}

				for ($j = 0; $j < $i; $j++) {
					if ($path === []) {
						$configs[$j] = [];
					} elseif (is_array($configs[$j])) { // a section that is not an array has no path to drop
						self::unsetPath($configs[$j], $path);
					}
				}
			}
		}

		return $resets ? [$resets, ...$configs] : array_values($configs);
	}


	/**
	 * @param  array<mixed>  $config
	 * @param  list<int|string>  $path
	 * @param  list<list<int|string>>  $paths
	 * @return array<mixed>
	 */
	private static function stripMergingMarkers(array $config, array $path, array &$paths): array
	{
		foreach ($config as $key => $val) {
			if ($val instanceof Config\Replacement) {
				$val = $val->value;
				$paths[] = [...$path, $key];
			} elseif (is_array($val) && isset($val[Config\Helpers::PREVENT_MERGING])) {
				unset($val[Config\Helpers::PREVENT_MERGING]);
				$paths[] = [...$path, $key];
			}

			$config[$key] = is_array($val)
				? self::stripMergingMarkers($val, [...$path, $key], $paths)
				: $val;
		}

		return $config;
	}


	/**
	 * @param  array<mixed>  $config
	 * @param  non-empty-list<int|string>  $path
	 */
	private static function unsetPath(array &$config, array $path): void
	{
		$key = array_shift($path);
		if (!$path) {
			unset($config[$key]);
		} elseif (isset($config[$key]) && is_array($config[$key])) {
			self::unsetPath($config[$key], $path);
		}
	}


	/**
	 * Generates the container class from the builder state and runs the Compile phase.
	 * @internal
	 */
	public function generateCode(): string
	{
		$generator = $this->createPhpGenerator();
		$this->class = $class = $generator->generate($this->className);
		$this->dependencies->add($this->builder->getDependencies());

		$this->runPhase(Phase::Compile);

		foreach ($this->extensions as $extension) {
			$generator->addInitialization($class, $extension);
		}

		return $this->sources . "\n" . $generator->toString($class);
	}


	/**
	 * Loads list of service definitions from configuration.
	 * @param  array<mixed>  $configList
	 */
	public function loadDefinitionsFromConfig(array $configList): void
	{
		$configList = Helpers::expand($configList, $this->builder->parameters);
		$extension = $this->extensions[self::Services];
		assert($extension instanceof Extensions\ServicesExtension);
		$config = $this->processSchema($extension->getConfigSchema(), [$configList], services: true);
		assert(is_array($config));
		$extension->loadDefinitions($config);
	}


	protected function createPhpGenerator(): PhpGenerator
	{
		return new PhpGenerator($this->builder);
	}
}
