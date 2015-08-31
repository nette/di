<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\DI;

use Nette;
use Nette\Utils\Validators;


/**
 * DI container compiler.
 */
class Compiler extends Nette\Object
{
	/** @var CompilerExtension[] */
	private $extensions = [];

	/** @var ContainerBuilder */
	private $builder;

	/** @var array */
	private $config = [];

	/** @var string[] of file names */
	private $dependencies = [];

	/** @var array reserved section names */
	private static $reserved = ['services' => 1, 'parameters' => 1];


	public function __construct(ContainerBuilder $builder = NULL)
	{
		$this->builder = $builder ?: new ContainerBuilder;
	}


	/**
	 * Add custom configurator extension.
	 * @return self
	 */
	public function addExtension($name, CompilerExtension $extension)
	{
		if (isset($this->extensions[$name]) || isset(self::$reserved[$name])) {
			throw new Nette\InvalidArgumentException("Name '$name' is already used or reserved.");
		}
		$this->extensions[$name] = $extension->setCompiler($this, $name);
		return $this;
	}


	/**
	 * @return array
	 */
	public function getExtensions($type = NULL)
	{
		return $type
			? array_filter($this->extensions, function ($item) use ($type) { return $item instanceof $type; })
			: $this->extensions;
	}


	/**
	 * @return ContainerBuilder
	 */
	public function getContainerBuilder()
	{
		return $this->builder;
	}


	/**
	 * Adds new configuration.
	 * @return self
	 */
	public function addConfig(array $config)
	{
		$this->config = Config\Helpers::merge($config, $this->config);
		return $this;
	}


	/**
	 * Adds new configuration from file.
	 * @return self
	 */
	public function loadConfig($file)
	{
		$loader = new Config\Loader;
		$this->addConfig($loader->load($file));
		$this->addDependencies($loader->getDependencies());
		return $this;
	}


	/**
	 * Returns configuration.
	 * @return array
	 */
	public function getConfig()
	{
		return $this->config;
	}


	/**
	 * Adds a files to the list of dependencies.
	 * @return self
	 */
	public function addDependencies(array $files)
	{
		$this->dependencies = array_merge($this->dependencies, $files);
		return $this;
	}


	/**
	 * Returns the unique list of dependent files.
	 * @return array
	 */
	public function getDependencies()
	{
		return array_values(array_unique(array_filter($this->dependencies)));
	}


	/**
	 * @return Nette\PhpGenerator\ClassType[]|string
	 */
	public function compile(array $config = NULL, $className = NULL, $parentName = NULL)
	{
		$this->config = $config ?: $this->config;
		$this->processParameters();
		$this->processExtensions();
		$this->processServices();
		$classes = $this->generateCode($className, $parentName);
		return func_num_args()
			? implode("\n\n\n", $classes) // back compatiblity
			: $classes;
	}


	/** @internal */
	public function processParameters()
	{
		if (isset($this->config['parameters'])) {
			$this->builder->parameters = Helpers::expand($this->config['parameters'], $this->config['parameters'], TRUE);
		}
	}


	/** @internal */
	public function processExtensions()
	{
		$last = $this->getExtensions(Extensions\InjectExtension::class);
		$this->extensions = array_merge(array_diff_key($this->extensions, $last), $last);

		$this->config = Helpers::expand(array_diff_key($this->config, self::$reserved), $this->builder->parameters)
			+ array_intersect_key($this->config, self::$reserved);

		foreach ($first = $this->getExtensions(Extensions\ExtensionsExtension::class) as $name => $extension) {
			$extension->setConfig(isset($this->config[$name]) ? $this->config[$name] : []);
			$extension->loadConfiguration();
		}

		$extensions = array_diff_key($this->extensions, $first);
		foreach (array_intersect_key($extensions, $this->config) as $name => $extension) {
			$extension->setConfig($this->config[$name] ?: []);
		}

		foreach ($extensions as $extension) {
			$extension->loadConfiguration();
		}

		if ($extra = array_diff_key($this->extensions, $extensions, $first)) {
			$extra = implode("', '", array_keys($extra));
			throw new Nette\DeprecatedException("Extensions '$extra' were added while container was being compiled.");

		} elseif ($extra = array_diff_key($this->config, self::$reserved, $this->extensions)) {
			$extra = implode("', '", array_keys($extra));
			throw new Nette\InvalidStateException("Found sections '$extra' in configuration, but corresponding extensions are missing.");
		}
	}


	/** @internal */
	public function processServices()
	{
		$this->parseServices($this->builder, $this->config);
	}


	/** @internal */
	public function generateCode($className, $parentName = NULL)
	{
		$this->builder->prepareClassList();

		foreach ($this->extensions as $extension) {
			$extension->beforeCompile();
			$this->dependencies[] = (new \ReflectionClass($extension))->getFileName();
		}

		$classes = $this->builder->generateClasses($className, $parentName);
		$classes[0]->addMethod('initialize');
		$this->addDependencies($this->builder->getDependencies());

		foreach ($this->extensions as $extension) {
			$extension->afterCompile($classes[0]);
		}
		return $classes;
	}


	/********************* tools ****************d*g**/


	/**
	 * Parses section 'services' from (unexpanded) configuration file.
	 * @return void
	 */
	public static function parseServices(ContainerBuilder $builder, array $config, $namespace = NULL)
	{
		$services = isset($config['services']) ? $config['services'] : [];
		$depths = [];
		foreach ($services as $name => $def) {
			$path = [];
			while (Config\Helpers::isInheriting($def)) {
				$path[] = $def;
				$def = isset($services[$def[Config\Helpers::EXTENDS_KEY]]) ? $services[$def[Config\Helpers::EXTENDS_KEY]] : [];
				if (in_array($def, $path, TRUE)) {
					throw new ServiceCreationException("Circular reference detected for service '$name'.");
				}
			}
			$depths[$name] = count($path);
		}
		array_multisort($depths, $services);

		foreach ($services as $name => $def) {
			if ((string) (int) $name === (string) $name) {
				$postfix = $def instanceof Statement && is_string($def->getEntity()) ? '.' . $def->getEntity() : (is_scalar($def) ? ".$def" : '');
				$name = (count($builder->getDefinitions()) + 1) . preg_replace('#\W+#', '_', $postfix);
			} elseif ($namespace) {
				$name = $namespace . '.' . $name;
			}

			$params = $builder->parameters;
			if (is_array($def) && isset($def['parameters'])) {
				foreach ((array) $def['parameters'] as $k => $v) {
					$v = explode(' ', is_int($k) ? $v : $k);
					$params[end($v)] = $builder::literal('$' . end($v));
				}
			}
			$def = Helpers::expand($def, $params);

			if (($parent = Config\Helpers::takeParent($def)) && $parent !== $name) {
				$builder->removeDefinition($name);
				$definition = $builder->addDefinition(
					$name,
					$parent === Config\Helpers::OVERWRITE ? NULL : unserialize(serialize($builder->getDefinition($parent))) // deep clone
				);
			} elseif ($builder->hasDefinition($name)) {
				$definition = $builder->getDefinition($name);
			} else {
				$definition = $builder->addDefinition($name);
			}

			try {
				static::parseService($definition, $def);
			} catch (\Exception $e) {
				throw new ServiceCreationException("Service '$name': " . $e->getMessage(), 0, $e);
			}
		}
	}


	/**
	 * Parses single service from configuration file.
	 * @return void
	 */
	public static function parseService(ServiceDefinition $definition, $config)
	{
		if ($config === NULL) {
			return;

		} elseif (is_string($config) && interface_exists($config)) {
			$config = ['class' => NULL, 'implement' => $config];

		} elseif ($config instanceof Statement && is_string($config->getEntity()) && interface_exists($config->getEntity())) {
			$config = ['class' => NULL, 'implement' => $config->getEntity(), 'factory' => array_shift($config->arguments)];

		} elseif (!is_array($config) || isset($config[0], $config[1])) {
			$config = ['class' => NULL, 'create' => $config];
		}

		if (array_key_exists('factory', $config)) {
			$config['create'] = $config['factory'];
			unset($config['factory']);
		};

		$known = ['class', 'create', 'arguments', 'setup', 'autowired', 'dynamic', 'inject', 'parameters', 'implement', 'run', 'tags'];
		if ($error = array_diff(array_keys($config), $known)) {
			throw new Nette\InvalidStateException(sprintf("Unknown or deprecated key '%s' in definition of service.", implode("', '", $error)));
		}

		$config = Helpers::filterArguments($config);

		$arguments = [];
		if (array_key_exists('arguments', $config)) {
			Validators::assertField($config, 'arguments', 'array');
			$arguments = $config['arguments'];
			$definition->setArguments($arguments);
		}

		if (array_key_exists('class', $config) || array_key_exists('create', $config)) {
			$definition->setClass(NULL);
			$definition->setFactory(NULL);
		}

		if (array_key_exists('class', $config)) {
			Validators::assertField($config, 'class', 'string|Nette\DI\Statement|null');
			if (!$config['class'] instanceof Statement) {
				$definition->setClass($config['class']);
			}
			$definition->setFactory($config['class'], $arguments);
		}

		if (array_key_exists('create', $config)) {
			Validators::assertField($config, 'create', 'callable|Nette\DI\Statement|null');
			$definition->setFactory($config['create'], $arguments);
		}

		if (isset($config['setup'])) {
			if (Config\Helpers::takeParent($config['setup'])) {
				$definition->setSetup([]);
			}
			Validators::assertField($config, 'setup', 'list');
			foreach ($config['setup'] as $id => $setup) {
				Validators::assert($setup, 'callable|Nette\DI\Statement', "setup item #$id");
				$definition->addSetup($setup);
			}
		}

		if (isset($config['parameters'])) {
			Validators::assertField($config, 'parameters', 'array');
			$definition->setParameters($config['parameters']);
		}

		if (isset($config['implement'])) {
			Validators::assertField($config, 'implement', 'string');
			$definition->setImplement($config['implement']);
			$definition->setAutowired(TRUE);
		}

		if (isset($config['autowired'])) {
			Validators::assertField($config, 'autowired', 'bool');
			$definition->setAutowired($config['autowired']);
		}

		if (isset($config['dynamic'])) {
			Validators::assertField($config, 'dynamic', 'bool');
			$definition->setDynamic($config['dynamic']);
		}

		if (isset($config['inject'])) {
			Validators::assertField($config, 'inject', 'bool');
			$definition->addTag(Extensions\InjectExtension::TAG_INJECT, $config['inject']);
		}

		if (isset($config['run'])) {
			$config['tags']['run'] = (bool) $config['run'];
		}

		if (isset($config['tags'])) {
			Validators::assertField($config, 'tags', 'array');
			if (Config\Helpers::takeParent($config['tags'])) {
				$definition->setTags([]);
			}
			foreach ($config['tags'] as $tag => $attrs) {
				if (is_int($tag) && is_string($attrs)) {
					$definition->addTag($attrs);
				} else {
					$definition->addTag($tag, $attrs);
				}
			}
		}
	}


	/**
	 * Use Helpers::filterArguments()
	 */
	public static function filterArguments(array $args)
	{
		return Helpers::filterArguments($args);
	}

}
