<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI;

use Nette;


/**
 * DI container compiler.
 */
class Compiler
{
	use Nette\SmartObject;

	private const
		SERVICES = 'services',
		PARAMETERS = 'parameters',
		RESERVED = [self::SERVICES => true, self::PARAMETERS => true];

	/** @var CompilerExtension[] */
	private $extensions = [];

	/** @var ContainerBuilder */
	private $builder;

	/** @var Config\Processor */
	private $configProcessor;

	/** @var array */
	private $config = [];

	/** @var array [section => array[]] */
	private $configs = [];

	/** @var string */
	private $sources = '';

	/** @var DependencyChecker */
	private $dependencies;

	/** @var string */
	private $className = 'Container';

	/** @var string[] */
	private $dynamicParams = [];


	public function __construct(ContainerBuilder $builder = null)
	{
		$this->builder = $builder ?: new ContainerBuilder;
		$this->dependencies = new DependencyChecker;
		$this->configProcessor = new Config\Processor($this->builder);
	}


	/**
	 * Add custom configurator extension.
	 * @return static
	 */
	public function addExtension(?string $name, CompilerExtension $extension)
	{
		if ($name === null) {
			$name = '_' . count($this->extensions);
		} elseif (isset($this->extensions[$name]) || isset(self::RESERVED[$name])) {
			throw new Nette\InvalidArgumentException("Name '$name' is already used or reserved.");
		}
		$lname = strtolower($name);
		foreach (array_keys($this->extensions + self::RESERVED) as $nm) {
			if ($lname === strtolower((string) $nm)) {
				throw new Nette\InvalidArgumentException("Name of extension '$name' has the same name as '$nm' in a case-insensitive manner.");
			}
		}
		$this->extensions[$name] = $extension->setCompiler($this, $name);
		return $this;
	}


	public function getExtensions(string $type = null): array
	{
		return $type
			? array_filter($this->extensions, function ($item) use ($type): bool { return $item instanceof $type; })
			: $this->extensions;
	}


	public function getContainerBuilder(): ContainerBuilder
	{
		return $this->builder;
	}


	/**
	 * @return static
	 */
	public function setClassName(string $className)
	{
		$this->className = $className;
		return $this;
	}


	/**
	 * Adds new configuration.
	 * @return static
	 */
	public function addConfig(array $config)
	{
		foreach ($config as $section => $data) {
			$this->configs[$section][] = $data;
		}
		$this->sources .= "// source: array\n";
		return $this;
	}


	/**
	 * Adds new configuration from file.
	 * @return static
	 */
	public function loadConfig(string $file, Config\Loader $loader = null)
	{
		$sources = $this->sources . "// source: $file\n";
		$loader = $loader ?: new Config\Loader;
		foreach ($loader->load($file, false) as $data) {
			$this->addConfig($data);
		}
		$this->dependencies->add($loader->getDependencies());
		$this->sources = $sources;
		return $this;
	}


	/**
	 * Returns configuration.
	 * @deprecated
	 */
	public function getConfig(): array
	{
		return $this->config;
	}


	/**
	 * Sets the names of dynamic parameters.
	 * @return static
	 */
	public function setDynamicParameterNames(array $names)
	{
		$this->dynamicParams = $names;
		return $this;
	}


	/**
	 * Adds dependencies to the list.
	 * @param  array  $deps  of ReflectionClass|\ReflectionFunctionAbstract|string
	 * @return static
	 */
	public function addDependencies(array $deps)
	{
		$this->dependencies->add(array_filter($deps));
		return $this;
	}


	/**
	 * Exports dependencies.
	 */
	public function exportDependencies(): array
	{
		return $this->dependencies->export();
	}


	public function compile(): string
	{
		$this->processParameters();
		$this->processExtensions();
		$this->processServices();
		return $this->generateCode();
	}


	/** @internal */
	public function processParameters(): void
	{
		$params = (new Config\Expect)->flatten($this->configs[self::PARAMETERS] ?? [[]], [self::PARAMETERS]);
		$this->config[self::PARAMETERS] = $params;

		foreach ($this->dynamicParams as $key) {
			$params[$key] = array_key_exists($key, $params)
				? new Definitions\DynamicParameter(Nette\PhpGenerator\Helpers::format('($this->parameters[?] \?\? ?)', $key, $params[$key]))
				: new Definitions\DynamicParameter(Nette\PhpGenerator\Helpers::format('$this->parameters[?]', $key));
		}
		$this->builder->parameters = Helpers::expand($params, $params, true);
	}


	/** @internal */
	public function processExtensions(): void
	{
		$configs = array_diff_key($this->configs, self::RESERVED);

		foreach ($first = $this->getExtensions(Extensions\ExtensionsExtension::class) as $name => $extension) {
			$schema = $extension->getConfigSchema();
			$config = $schema->flatten($configs[$name] ?? [], [$name]);
			$config = Helpers::expand($config, $this->builder->parameters);
			$config = $schema->complete($config, [$name]);
			$extension->setConfig($this->config[$name] = $config);
			$extension->loadConfiguration();
		}

		$last = $this->getExtensions(Extensions\InjectExtension::class);
		$this->extensions = array_merge(array_diff_key($this->extensions, $last), $last);

		$extensions = array_diff_key($this->extensions, $first);
		foreach ($extensions as $name => $extension) {
			$schema = $extension->getConfigSchema();
			$config = $schema->flatten($configs[$name] ?? [], [$name]);
			$config = Helpers::expand($config, $this->builder->parameters);
			$config = $schema->complete($config, [$name]);
			$extension->setConfig($this->config[$name] = $config);
		}

		foreach ($extensions as $extension) {
			$extension->loadConfiguration();
		}

		if ($extra = array_diff_key($this->extensions, $extensions, $first)) {
			$extra = implode("', '", array_keys($extra));
			throw new Nette\DeprecatedException("Extensions '$extra' were added while container was being compiled.");

		} elseif ($extra = key(array_diff_key($configs, self::RESERVED, $this->extensions))) {
			$hint = Nette\Utils\ObjectHelpers::getSuggestion(array_keys(self::RESERVED + $this->extensions), $extra);
			throw new InvalidConfigurationException(
				"Found section '$extra' in configuration, but corresponding extension is missing"
				. ($hint ? ", did you mean '$hint'?" : '.')
			);
		}
	}


	/** @internal */
	public function processServices(): void
	{
		foreach ($this->configs[self::SERVICES] ?? [] as $config) {
			$this->config[self::SERVICES] = $this->configProcessor->mergeConfigs($config, $this->config[self::SERVICES] ?? null);
		}
		$this->loadDefinitionsFromConfig($this->config[self::SERVICES] ?? []);
	}


	/** @internal */
	public function generateCode(): string
	{
		$this->builder->resolve();

		foreach ($this->extensions as $extension) {
			$extension->beforeCompile();
			$this->dependencies->add([(new \ReflectionClass($extension))->getFileName()]);
		}

		$this->builder->complete();

		$generator = new PhpGenerator($this->builder);
		$class = $generator->generate($this->className);
		$class->addMethod('initialize');
		$this->dependencies->add($this->builder->getDependencies());

		foreach ($this->extensions as $extension) {
			$extension->afterCompile($class);
		}

		return $this->sources . "\n" . $generator->toString($class);
	}


	/**
	 * Loads list of service definitions from configuration.
	 */
	public function loadDefinitionsFromConfig(array $configList): void
	{
		$configList = array_map([$this->configProcessor, 'normalizeConfig'], $configList);
		$this->configProcessor->loadDefinitions($configList);
	}


	/**
	 * @deprecated use non-static Compiler::loadDefinitionsFromConfig()
	 */
	public static function loadDefinitions(): void
	{
		throw new Nette\DeprecatedException(__METHOD__ . '() is deprecated, use non-static Compiler::loadDefinitionsFromConfig(array $configList).');
	}


	/**
	 * @deprecated use non-static Compiler::loadDefinitionsFromConfig()
	 */
	public static function loadDefinition(): void
	{
		throw new Nette\DeprecatedException(__METHOD__ . '() is deprecated, use non-static Compiler::loadDefinitionsFromConfig(array $configList).');
	}
}
