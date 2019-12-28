<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI;

use Nette;
use Nette\Schema;


/**
 * DI container compiler.
 */
class Compiler
{
	use Nette\SmartObject;

	private const
		SERVICES = 'services',
		PARAMETERS = 'parameters',
		DI = 'di';

	/** @var CompilerExtension[] */
	private $extensions = [];

	/** @var ContainerBuilder */
	private $builder;

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


	public function __construct(ContainerBuilder $builder = null)
	{
		$this->builder = $builder ?: new ContainerBuilder;
		$this->dependencies = new DependencyChecker;
		$this->addExtension(self::SERVICES, new Extensions\ServicesExtension);
		$this->addExtension(self::PARAMETERS, new Extensions\ParametersExtension($this->configs));
	}


	/**
	 * Add custom configurator extension.
	 * @return static
	 */
	public function addExtension(?string $name, CompilerExtension $extension)
	{
		if ($name === null) {
			$name = '_' . count($this->extensions);
		} elseif (isset($this->extensions[$name])) {
			throw new Nette\InvalidArgumentException("Name '$name' is already used or reserved.");
		}
		$lname = strtolower($name);
		foreach (array_keys($this->extensions) as $nm) {
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


	/** @return static */
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
		assert($this->extensions[self::PARAMETERS] instanceof Extensions\ParametersExtension);
		$this->extensions[self::PARAMETERS]->dynamicParams = $names;
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


	/** @return static */
	public function addExportedTag(string $tag)
	{
		if (isset($this->extensions[self::DI])) {
			assert($this->extensions[self::DI] instanceof Extensions\DIExtension);
			$this->extensions[self::DI]->exportedTags[$tag] = true;
		}
		return $this;
	}


	/** @return static */
	public function addExportedType(string $type)
	{
		if (isset($this->extensions[self::DI])) {
			assert($this->extensions[self::DI] instanceof Extensions\DIExtension);
			$this->extensions[self::DI]->exportedTypes[$type] = true;
		}
		return $this;
	}


	public function compile(): string
	{
		$this->processExtensions();
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

		$extensions = array_diff_key($this->extensions, $first, [self::SERVICES => 1]);
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

		if ($extra = array_diff_key($this->extensions, $extensions, $first, [self::SERVICES => 1])) {
			$extra = implode("', '", array_keys($extra));
			throw new Nette\DeprecatedException("Extensions '$extra' were added while container was being compiled.");

		} elseif ($extra = key(array_diff_key($this->configs, $this->extensions))) {
			$hint = Nette\Utils\Helpers::getSuggestion(array_keys($this->extensions), $extra);
			throw new InvalidConfigurationException(
				"Found section '$extra' in configuration, but corresponding extension is missing"
				. ($hint ? ", did you mean '$hint'?" : '.')
			);
		}
	}


	/**
	 * Merges and validates configurations against scheme.
	 * @return array|object
	 */
	private function processSchema(Schema\Schema $schema, array $configs, $name = null)
	{
		$processor = new Schema\Processor;
		$processor->onNewContext[] = function (Schema\Context $context) use ($name) {
			$context->path = $name ? [$name] : [];
			$context->dynamics = &$this->extensions[self::PARAMETERS]->dynamicValidators;
		};
		try {
			return $processor->processMultiple($schema, $configs);
		} catch (Schema\ValidationException $e) {
			throw new Nette\DI\InvalidConfigurationException($e->getMessage());
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

		$this->builder->complete();

		$generator = new PhpGenerator($this->builder);
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
	 */
	public function loadDefinitionsFromConfig(array $configList): void
	{
		$extension = $this->extensions[self::SERVICES];
		assert($extension instanceof Extensions\ServicesExtension);
		$extension->loadDefinitions($this->processSchema($extension->getConfigSchema(), [$configList]));
	}


	/** @deprecated use non-static Compiler::loadDefinitionsFromConfig() */
	public static function loadDefinitions(): void
	{
		throw new Nette\DeprecatedException(__METHOD__ . '() is deprecated, use non-static Compiler::loadDefinitionsFromConfig(array $configList).');
	}


	/** @deprecated use non-static Compiler::loadDefinitionsFromConfig() */
	public static function loadDefinition(): void
	{
		throw new Nette\DeprecatedException(__METHOD__ . '() is deprecated, use non-static Compiler::loadDefinitionsFromConfig(array $configList).');
	}
}
