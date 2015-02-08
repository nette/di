<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\DI;

use Nette,
	Nette\Utils\Validators;


/**
 * DI container compiler.
 *
 * @author     David Grudl
 */
class Compiler extends Nette\Object
{
	/** @var CompilerExtension[] */
	private $extensions = array();

	/** @var ContainerBuilder */
	private $builder;

	/** @var array */
	private $config;

	/** @var array reserved section names */
	private static $reserved = array('services' => 1, 'parameters' => 1);


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
		if (isset(self::$reserved[$name])) {
			throw new Nette\InvalidArgumentException("Name '$name' is reserved.");
		}
		if (isset($this->config[$name])) {
			$this->config[$name] = $tmp = Helpers::expand($this->config[$name], $this->builder->parameters);
			unset($tmp['services']);
			$extension->setConfig($tmp);
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
			? array_filter($this->extensions, function($item) use ($type) { return $item instanceof $type; })
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
	 * Returns configuration.
	 * @return array
	 */
	public function getConfig()
	{
		return $this->config;
	}


	/**
	 * @return string
	 */
	public function compile(array $config, $className, $parentName = NULL)
	{
		$this->config = $config;
		$this->processParameters();
		$this->processExtensions();
		$this->processServices();
		return $this->generateCode($className, $parentName);
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
		foreach ($this->extensions as $name => $extension) {
			if (isset($this->config[$name])) {
				$this->config[$name] = $tmp = Helpers::expand($this->config[$name], $this->builder->parameters);
				unset($tmp['services']);
				$this->extensions[$name]->setConfig($tmp);
			}
		}
		for ($i = 0; $slice = array_slice($this->extensions, $i, 1, TRUE); $i++) {
			$name = key($slice);
			$this->extensions[$name]->loadConfiguration();
		}

		if ($extra = array_diff_key($this->config, self::$reserved, $this->extensions)) {
			$extra = implode("', '", array_keys($extra));
			throw new Nette\InvalidStateException("Found sections '$extra' in configuration, but corresponding extensions are missing.");
		}
	}


	/** @internal */
	public function processServices()
	{
		$this->parseServices($this->builder, $this->config);

		foreach ($this->extensions as $name => $extension) {
			if (isset($this->config[$name])) {
				$this->parseServices($this->builder, $this->config[$name], $name);
			}
		}
	}


	/** @internal */
	public function generateCode($className, $parentName = NULL)
	{
		$this->builder->prepareClassList();

		foreach ($this->extensions as $extension) {
			$extension->beforeCompile();
			$rc = new \ReflectionClass($extension);
			$this->builder->addDependency($rc->getFileName());
		}

		$classes = $this->builder->generateClasses($className, $parentName);
		$classes[0]->addMethod('initialize');

		foreach ($this->extensions as $extension) {
			$extension->afterCompile($classes[0]);
		}
		return implode("\n\n\n", $classes);
	}


	/********************* tools ****************d*g**/


	/**
	 * Parses section 'services' from (unexpanded) configuration file.
	 * @return void
	 */
	public static function parseServices(ContainerBuilder $builder, array $config, $namespace = NULL)
	{
		if (!empty($config['factories'])) {
			throw new Nette\DeprecatedException("Section 'factories' is deprecated, move definitions to section 'services' and append key 'autowired: no'.");
		}

		$services = isset($config['services']) ? $config['services'] : array();
		$depths = array();
		foreach ($services as $name => $def) {
			$path = array();
			while (Config\Helpers::isInheriting($def)) {
				$path[] = $def;
				$def = isset($services[$def[Config\Helpers::EXTENDS_KEY]]) ? $services[$def[Config\Helpers::EXTENDS_KEY]] : array();
				if (in_array($def, $path, TRUE)) {
					throw new ServiceCreationException("Circular reference detected for service '$name'.");
				}
			}
			$depths[$name] = count($path);
		}
		array_multisort($depths, $services);

		foreach ($services as $origName => $def) {
			if ((string) (int) $origName === (string) $origName) {
				$name = (count($builder->getDefinitions()) + 1)
					. preg_replace('#\W+#', '_', $def instanceof Statement ? '.' . $def->getEntity() : (is_scalar($def) ? ".$def" : ''));
			} else {
				$name = ($namespace ? $namespace . '.' : '') . strtr($origName, '\\', '_');
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
				throw new ServiceCreationException("Service '$name': " . $e->getMessage(), NULL, $e);
			}

			if ($definition->getClass() === 'self' || ($definition->getFactory() && $definition->getFactory()->getEntity() === 'self')) {
				throw new Nette\DeprecatedException("Replace service definition '$origName: self' with '- $origName'.");
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
			$config = array('class' => NULL, 'implement' => $config);

		} elseif ($config instanceof Statement && is_string($config->getEntity()) && interface_exists($config->getEntity())) {
			$config = array('class' => NULL, 'implement' => $config->getEntity(), 'factory' => array_shift($config->arguments));

		} elseif (!is_array($config) || isset($config[0], $config[1])) {
			$config = array('class' => NULL, 'create' => $config);
		}

		if (array_key_exists('factory', $config)) {
			$config['create'] = $config['factory'];
			unset($config['factory']);
		};

		$known = array('class', 'create', 'arguments', 'setup', 'autowired', 'dynamic', 'inject', 'parameters', 'implement', 'run', 'tags');
		if ($error = array_diff(array_keys($config), $known)) {
			throw new Nette\InvalidStateException(sprintf("Unknown or deprecated key '%s' in definition of service.", implode("', '", $error)));
		}

		$config = self::filterArguments($config);

		$arguments = array();
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
				$definition->setSetup(array());
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
				$definition->setTags(array());
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
	 * Removes ... and process constants recursively.
	 * @return array
	 */
	public static function filterArguments(array $args)
	{
		foreach ($args as $k => $v) {
			if ($v === '...') {
				unset($args[$k]);
			} elseif (is_string($v) && preg_match('#^[\w\\\\]*::[A-Z][A-Z0-9_]*\z#', $v, $m)) {
				$args[$k] = ContainerBuilder::literal(ltrim($v, ':'));
			} elseif (is_array($v)) {
				$args[$k] = self::filterArguments($v);
			} elseif ($v instanceof Statement) {
				$tmp = self::filterArguments(array($v->getEntity()));
				$args[$k] = new Statement($tmp[0], self::filterArguments($v->arguments));
			}
		}
		return $args;
	}

}
