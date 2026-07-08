<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI;

use Nette;
use Nette\DI\Compiler\DependencyChecker;
use Nette\DI\Compiler\PhpGenerator;
use Nette\Schema;
use function count, is_array, sprintf;


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
	 * Adds new configuration.
	 * @param  array<string, mixed>  $config
	 */
	public function addConfig(array $config): static
	{
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
	 * Sets the names of dynamic parameters.
	 * @param  string[]  $names
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
			$this->dependencies->add(array_filter([(new \ReflectionClass($extension))->getFileName()]));
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
		foreach ($this->configs[self::Closures] ?? [] as $closures) {
			foreach ($closures as $closure) {
				$closure($this->builder);
			}
		}
	}


	/**
	 * Validates extension config and calls its registerHooks().
	 */
	private function initExtension(string $name): void
	{
		$extension = $this->extensions[$name];
		$config = $this->processSchema($extension->getConfigSchema(), $this->configs[$name] ?? [], $name);
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
		foreach ((new \ReflectionClass($extension))->getMethods() as $method) {
			foreach ($method->getAttributes(Attributes\Hook::class) as $attr) {
				$hook = $attr->newInstance();
				if ($hook->phase === Phase::Setup) {
					$before = array_merge($before, (array) ($hook->before ?? []));
					$after = array_merge($after, (array) ($hook->after ?? []));
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
			($hook['callback'])($phase === Phase::Compile ? $this->class : $this->builder);
			if ($phase === Phase::Modify) {
				$this->builder->resolve(); // re-resolve for definitions added by the hook
			}
		}

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
		foreach ($this->schedule->drainForExtension($phase, $extension) as $hook) {
			($hook['callback'])($this->builder);
		}

		$this->schedule->markIdle();
	}


	/**
	 * Merges and validates configurations against scheme.
	 * @param  array<mixed[]>  $configs
	 * @return array<string, mixed>|object
	 */
	private function processSchema(Schema\Schema $schema, array $configs, ?string $name = null): array|object
	{
		$processor = new Schema\Processor;
		$processor->onNewContext[] = function (Schema\Context $context) use ($name) {
			$context->path = $name ? [$name] : [];
			$context->dynamics = &$this->extensions[self::Parameters]->dynamicValidators;
		};
		try {
			$res = $processor->processMultiple($schema, array_values($configs));
		} catch (Schema\ValidationException $e) {
			throw new Nette\DI\InvalidConfigurationException($e->getMessage());
		}

		foreach ($processor->getWarnings() as $warning) {
			trigger_error($warning, E_USER_DEPRECATED);
		}

		return $res;
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
		$config = $this->processSchema($extension->getConfigSchema(), [$configList]);
		assert(is_array($config));
		$extension->loadDefinitions($config);
	}


	protected function createPhpGenerator(): PhpGenerator
	{
		return new PhpGenerator($this->builder);
	}
}
