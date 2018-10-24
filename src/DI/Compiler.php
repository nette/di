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

	/** @var CompilerExtension[] */
	private $extensions = [];

	/** @var ContainerBuilder */
	private $builder;

	/** @var Config\Processor */
	private $configProcessor;

	/** @var array */
	private $config = [];

	/** @var array */
	private $serviceConfigs = [];

	/** @var DependencyChecker */
	private $dependencies;

	/** @var string */
	private $className = 'Container';

	/** @var string[] */
	private $dynamicParams = [];

	/** @var array reserved section names */
	private static $reserved = ['services' => 1, 'parameters' => 1];


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
		} elseif (isset($this->extensions[$name]) || isset(self::$reserved[$name])) {
			throw new Nette\InvalidArgumentException("Name '$name' is already used or reserved.");
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
		if (isset($config['services'])) {
			$this->serviceConfigs[] = $config['services'];
		}
		$this->config = $this->configProcessor->merge($this->config, $config);
		return $this;
	}


	/**
	 * Adds new configuration from file.
	 * @return static
	 */
	public function loadConfig(string $file, Config\Loader $loader = null)
	{
		$loader = $loader ?: new Config\Loader;
		$this->addConfig($loader->load($file));
		$this->dependencies->add($loader->getDependencies());
		return $this;
	}


	/**
	 * Returns configuration.
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
		$params = $this->config['parameters'] ?? [];
		foreach ($this->dynamicParams as $key) {
			$params[$key] = array_key_exists($key, $params)
				? ContainerBuilder::literal('$this->parameters[?] \?\? ?', [$key, $params[$key]])
				: ContainerBuilder::literal('$this->parameters[?]', [$key]);
		}
		$this->builder->parameters = Helpers::expand($params, $params, true);
	}


	/** @internal */
	public function processExtensions(): void
	{
		$config = array_diff_key($this->config, self::$reserved);
		$config = Helpers::expand($config, $this->builder->parameters);

		foreach ($first = $this->getExtensions(Extensions\ExtensionsExtension::class) as $name => $extension) {
			$extension->setConfig($config[$name] ?? []);
			$extension->loadConfiguration();
		}

		$last = $this->getExtensions(Extensions\InjectExtension::class);
		$this->extensions = array_merge(array_diff_key($this->extensions, $last), $last);

		$extensions = array_diff_key($this->extensions, $first);
		foreach (array_intersect_key($extensions, $config) as $name => $extension) {
			$extension->setConfig($config[$name] ?: []);
		}

		foreach ($extensions as $extension) {
			$extension->loadConfiguration();
		}

		if ($extra = array_diff_key($this->extensions, $extensions, $first)) {
			$extra = implode("', '", array_keys($extra));
			throw new Nette\DeprecatedException("Extensions '$extra' were added while container was being compiled.");

		} elseif ($extra = key(array_diff_key($config, $this->extensions))) {
			$hint = Nette\Utils\ObjectHelpers::getSuggestion(array_keys(self::$reserved + $this->extensions), $extra);
			throw new Nette\InvalidStateException(
				"Found section '$extra' in configuration, but corresponding extension is missing"
				. ($hint ? ", did you mean '$hint'?" : '.')
			);
		}
	}


	/** @internal */
	public function processServices(): void
	{
		foreach ($this->serviceConfigs as $config) {
			$this->loadDefinitions($config);
		}
	}


	/** @internal */
	public function generateCode(): string
	{
		$this->builder->resolve();

		foreach ($this->extensions as $extension) {
			$extension->beforeCompile();
			$this->dependencies->add([(new \ReflectionClass($extension))->getFileName()]);
		}

		$generator = new PhpGenerator($this->builder);
		$class = $generator->generate($this->className);
		$class->addMethod('initialize');
		$this->dependencies->add($this->builder->getDependencies());

		foreach ($this->extensions as $extension) {
			$extension->afterCompile($class);
		}

		return "declare(strict_types=1);\n\n\n" . $class->__toString();
	}


	/********************* tools ****************d*g**/


	/**
	 * Adds service definitions from configuration.
	 */
	public function loadDefinitions(array $services, string $namespace = null): void
	{
		foreach ($services as &$def) {
			$def = $this->configProcessor->normalizeStructure($def);
		}
		if ($namespace) {
			$services = $this->configProcessor->applyNamespace($services, $namespace);
		}
		$this->configProcessor->loadDefinitions($services, $namespace);
	}


	/**
	 * @deprecated
	 */
	public static function loadDefinition(): void
	{
		throw new Nette\DeprecatedException(__METHOD__ . '() is deprecated.');
	}
}
