<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI;

use Nette;
use Nette\Utils\Validators;


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
	}


	/**
	 * Add custom configurator extension.
	 * @param  string|null
	 * @return static
	 */
	public function addExtension($name, CompilerExtension $extension)
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
			? array_filter($this->extensions, function ($item) use ($type) { return $item instanceof $type; })
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
		$this->config = Config\Helpers::merge($config, $this->config);
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
	 * @param  array of ReflectionClass|\ReflectionFunctionAbstract|string
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
		$classes = $this->generateCode();
		array_unshift($classes, 'declare(strict_types=1);');
		return implode("\n\n\n", $classes);
	}


	/** @internal */
	public function processParameters()
	{
		$params = isset($this->config['parameters']) ? $this->config['parameters'] : [];
		foreach ($this->dynamicParams as $key) {
			$params[$key] = array_key_exists($key, $params)
				? ContainerBuilder::literal('$this->parameters[?] \?\? ?', [$key, $params[$key]])
				: ContainerBuilder::literal('$this->parameters[?]', [$key]);
		}
		$this->builder->parameters = Helpers::expand($params, $params, true);
	}


	/** @internal */
	public function processExtensions()
	{
		$this->config = Helpers::expand(array_diff_key($this->config, self::$reserved), $this->builder->parameters)
			+ array_intersect_key($this->config, self::$reserved);

		foreach ($first = $this->getExtensions(Extensions\ExtensionsExtension::class) as $name => $extension) {
			$extension->setConfig($this->config[$name] ?? []);
			$extension->loadConfiguration();
		}

		$last = $this->getExtensions(Extensions\InjectExtension::class);
		$this->extensions = array_merge(array_diff_key($this->extensions, $last), $last);

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

		} elseif ($extra = key(array_diff_key($this->config, self::$reserved, $this->extensions))) {
			$hint = Nette\Utils\ObjectMixin::getSuggestion(array_keys(self::$reserved + $this->extensions), $extra);
			throw new Nette\InvalidStateException(
				"Found section '$extra' in configuration, but corresponding extension is missing"
				. ($hint ? ", did you mean '$hint'?" : '.')
			);
		}
	}


	/** @internal */
	public function processServices()
	{
		foreach ($this->serviceConfigs as $config) {
			self::loadDefinitions($this->builder, $config);
		}
	}


	/** @internal */
	public function generateCode(): array
	{
		$this->builder->prepareClassList();

		foreach ($this->extensions as $extension) {
			$extension->beforeCompile();
			$this->dependencies->add([(new \ReflectionClass($extension))->getFileName()]);
		}

		$generator = new PhpGenerator($this->builder);
		$classes = $generator->generate($this->className);
		$classes[0]->addMethod('initialize');
		$this->dependencies->add($this->builder->getDependencies());

		foreach ($this->extensions as $extension) {
			$extension->afterCompile($classes[0]);
		}
		return $classes;
	}


	/********************* tools ****************d*g**/


	/**
	 * Adds service definitions from configuration.
	 * @return void
	 */
	public static function loadDefinitions(ContainerBuilder $builder, array $services, string $namespace = null)
	{
		foreach ($services as $name => $def) {
			if (is_int($name)) {
				$postfix = $def instanceof Statement && is_string($def->getEntity()) ? '.' . $def->getEntity() : (is_scalar($def) ? ".$def" : '');
				$name = (count($builder->getDefinitions()) + 1) . preg_replace('#\W+#', '_', $postfix);
			} elseif (preg_match('#^@[\w\\\\]+\z#', $name)) {
				$name = $builder->getByType(substr($name, 1), true);
			} elseif ($namespace) {
				$name = $namespace . '.' . $name;
			}

			if ($def === false) {
				$builder->removeDefinition($name);
				continue;
			}
			if ($namespace) {
				$def = Helpers::prefixServiceName($def, $namespace);
			}

			$params = $builder->parameters;
			if (is_array($def) && isset($def['parameters'])) {
				foreach ((array) $def['parameters'] as $k => $v) {
					$v = explode(' ', is_int($k) ? $v : $k);
					$params[end($v)] = $builder::literal('$' . end($v));
				}
			}
			$def = Helpers::expand($def, $params);

			if (is_array($def) && !empty($def['alteration']) && !$builder->hasDefinition($name)) {
				throw new ServiceCreationException("Service '$name': missing original definition for alteration.");
			}
			if (Config\Helpers::takeParent($def)) {
				$builder->removeDefinition($name);
			}
			$definition = $builder->hasDefinition($name)
				? $builder->getDefinition($name)
				: $builder->addDefinition($name);

			try {
				static::loadDefinition($definition, $def);
			} catch (\Exception $e) {
				throw new ServiceCreationException("Service '$name': " . $e->getMessage(), 0, $e);
			}
		}
	}


	/**
	 * Parses single service definition from configuration.
	 * @return void
	 */
	public static function loadDefinition(ServiceDefinition $definition, $config)
	{
		if ($config === null) {
			return;

		} elseif (is_string($config) && interface_exists($config)) {
			$config = ['class' => null, 'implement' => $config];

		} elseif ($config instanceof Statement && is_string($config->getEntity()) && interface_exists($config->getEntity())) {
			$config = ['class' => null, 'implement' => $config->getEntity(), 'factory' => array_shift($config->arguments)];

		} elseif (!is_array($config) || isset($config[0], $config[1])) {
			$config = ['class' => null, 'factory' => $config];
		}

		$known = ['class', 'factory', 'arguments', 'setup', 'autowired', 'dynamic', 'inject', 'parameters', 'implement', 'run', 'tags', 'alteration'];
		if ($error = array_diff(array_keys($config), $known)) {
			$hints = array_filter(array_map(function ($error) use ($known) {
				return Nette\Utils\ObjectMixin::getSuggestion($known, $error);
			}, $error));
			$hint = $hints ? ", did you mean '" . implode("', '", $hints) . "'?" : '.';
			throw new Nette\InvalidStateException(sprintf("Unknown key '%s' in definition of service$hint", implode("', '", $error)));
		}

		$config = Helpers::filterArguments($config);

		if (array_key_exists('class', $config) || array_key_exists('factory', $config)) {
			$definition->setClass(null);
			$definition->setFactory(null);
		}

		if (array_key_exists('class', $config)) {
			Validators::assertField($config, 'class', 'string|Nette\DI\Statement|null');
			if (!$config['class'] instanceof Statement) {
				$definition->setClass($config['class']);
			}
			$definition->setFactory($config['class']);
		}

		if (array_key_exists('factory', $config)) {
			Validators::assertField($config, 'factory', 'callable|Nette\DI\Statement|null');
			$definition->setFactory($config['factory']);
		}

		if (array_key_exists('arguments', $config)) {
			Validators::assertField($config, 'arguments', 'array');
			$arguments = $config['arguments'];
			if (!Config\Helpers::takeParent($arguments) && !Nette\Utils\Arrays::isList($arguments) && $definition->getFactory()) {
				$arguments += $definition->getFactory()->arguments;
			}
			$definition->setArguments($arguments);
		}

		if (isset($config['setup'])) {
			if (Config\Helpers::takeParent($config['setup'])) {
				$definition->setSetup([]);
			}
			Validators::assertField($config, 'setup', 'list');
			foreach ($config['setup'] as $id => $setup) {
				Validators::assert($setup, 'callable|Nette\DI\Statement|array:1', "setup item #$id");
				if (is_array($setup)) {
					$setup = new Statement(key($setup), array_values($setup));
				}
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
			$definition->setAutowired(true);
		}

		if (isset($config['autowired'])) {
			Validators::assertField($config, 'autowired', 'bool|string|array');
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
}
