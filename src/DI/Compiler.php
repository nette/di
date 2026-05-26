<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI;

use Nette;
use Nette\Schema;
use function count, is_array, sprintf;


/**
 * DI container compiler.
 */
class Compiler
{
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


	public function __construct(
		private readonly ContainerBuilder $builder = new ContainerBuilder,
	) {
		$this->dependencies = new DependencyChecker;
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
		$this->processExtensions();
		$this->processBeforeCompile();
		return $this->generateCode();
	}


	/** @internal */
	public function processExtensions(): void
	{
		$first = $this->getExtensions(Extensions\ParametersExtension::class) + $this->getExtensions(Extensions\ExtensionsExtension::class);
		foreach ($first as $name => $extension) {
			$config = $this->processSchema($extension->getConfigSchema(), $this->configs[$name] ?? [], $name);
			$extension->setConfig($this->config[$name] = $config);
			$extension->loadConfiguration();
		}

		$last = $this->getExtensions(Extensions\InjectExtension::class);
		$this->extensions = array_merge(array_diff_key($this->extensions, $last), $last);

		if ($decorator = $this->getExtensions(Extensions\DecoratorExtension::class)) {
			Nette\Utils\Arrays::insertBefore($this->extensions, key($decorator), $this->getExtensions(Extensions\SearchExtension::class));
		}

		$extensions = array_diff_key($this->extensions, $first, [self::Services => 1]);
		foreach ($extensions as $name => $extension) {
			$config = $this->processSchema($extension->getConfigSchema(), $this->configs[$name] ?? [], $name);
			$extension->setConfig($this->config[$name] = $config);
		}

		foreach ($extensions as $extension) {
			$extension->loadConfiguration();
		}

		foreach ($this->getExtensions(Extensions\ServicesExtension::class) as $name => $extension) {
			$config = $this->processSchema($extension->getConfigSchema(), $this->configs[$name] ?? [], $name);
			$extension->setConfig($this->config[$name] = $config);
			$extension->loadConfiguration();
		}

		if ($extra = array_diff_key($this->extensions, $extensions, $first, [self::Services => 1])) {
			throw new Nette\DeprecatedException(sprintf(
				"Extensions '%s' were added while container was being compiled.",
				implode("', '", array_keys($extra)),
			));

		} elseif ($extra = key(array_diff_key($this->configs, $this->extensions))) {
			$hint = Nette\Utils\Helpers::getSuggestion(array_keys($this->extensions), $extra);
			throw new InvalidConfigurationException(
				sprintf("Found section '%s' in configuration, but corresponding extension is missing", $extra)
				. ($hint ? ", did you mean '$hint'?" : '.'),
			);
		}
	}


	private function processBeforeCompile(): void
	{
		$this->builder->resolve();

		foreach ($this->extensions as $extension) {
			$extension->beforeCompile();
			if ($file = (new \ReflectionClass($extension))->getFileName()) {
				$this->dependencies->add([$file]);
			}
		}

		$this->builder->complete();
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


	/** @internal */
	public function generateCode(): string
	{
		$generator = $this->createPhpGenerator();
		$class = $generator->generate($this->className);
		$this->dependencies->add($this->builder->getDependencies());

		foreach ($this->extensions as $extension) {
			$extension->afterCompile($class);
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
