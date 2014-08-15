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
 *
 * @property-read CompilerExtension[] $extensions
 * @property-read ContainerBuilder $containerBuilder
 * @property-read array $config
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
	private static $reserved = array('services' => 1, 'factories' => 1, 'parameters' => 1);


	/**
	 * Add custom configurator extension.
	 * @return self
	 */
	public function addExtension($name, CompilerExtension $extension)
	{
		if (isset(self::$reserved[$name])) {
			throw new Nette\InvalidArgumentException("Name '$name' is reserved.");
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
		if ($this->builder === NULL) {
			$this->builder = new ContainerBuilder;
		}
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
	public function compile(array $config, $className, $parentName)
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
			$this->getContainerBuilder()->parameters = Helpers::expand($this->config['parameters'], $this->config['parameters'], TRUE);
		}
	}


	/** @internal */
	public function processExtensions()
	{
		for ($i = 0; $slice = array_slice($this->extensions, $i, 1, TRUE); $i++) {
			$name = key($slice);
			if (isset($this->config[$name])) {
				$this->config[$name] = $this->getContainerBuilder()->expand($this->config[$name]);
			}
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
		$this->parseServices($this->getContainerBuilder(), $this->config);

		foreach ($this->extensions as $name => $extension) {
			if (isset($this->config[$name])) {
				$this->parseServices($this->getContainerBuilder(), $this->config[$name], $name);
			}
		}
	}


	/** @internal */
	public function generateCode($className, $parentName)
	{
		$this->getContainerBuilder()->prepareClassList();

		foreach ($this->extensions as $extension) {
			$extension->beforeCompile();
			$this->getContainerBuilder()->addDependency(Nette\Reflection\ClassType::from($extension)->getFileName());
		}

		$classes = $this->getContainerBuilder()->generateClasses($className, $parentName);
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
			trigger_error("Section 'factories' is deprecated, move definitions to section 'services' and append key 'autowired: no'.", E_USER_DEPRECATED);
		}

		$services = isset($config['services']) ? $config['services'] : array();
		$factories = isset($config['factories']) ? $config['factories'] : array();
		$all = array_merge($services, $factories);

		$depths = array();
		foreach ($all as $name => $def) {
			$path = array();
			while (Config\Helpers::isInheriting($def)) {
				$path[] = $def;
				$def = isset($all[$def[Config\Helpers::EXTENDS_KEY]]) ? $all[$def[Config\Helpers::EXTENDS_KEY]] : array();
				if (in_array($def, $path, TRUE)) {
					throw new ServiceCreationException("Circular reference detected for service '$name'.");
				}
			}
			$depths[$name] = count($path);
		}
		array_multisort($depths, $all);

		foreach ($all as $origName => $def) {
			if ((string) (int) $origName === (string) $origName) {
				$name = count($builder->getDefinitions())
					. preg_replace('#\W+#', '_', $def instanceof Statement ? ".$def->entity" : (is_scalar($def) ? ".$def" : ''));
			} elseif (array_key_exists($origName, $services) && array_key_exists($origName, $factories)) {
				throw new ServiceCreationException("It is not allowed to use services and factories with the same name: '$origName'.");
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

			if ($definition->class === 'self') {
				$definition->class = $origName;
				trigger_error("Replace service definition '$origName: self' with '- $origName'.", E_USER_DEPRECATED);
			}
			if ($definition->factory && $definition->factory->entity === 'self') {
				$definition->factory->entity = $origName;
				trigger_error("Replace service definition '$origName: self' with '- $origName'.", E_USER_DEPRECATED);
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

		} elseif ($config instanceof Statement && is_string($config->entity) && interface_exists($config->entity)) {
			$config = array('class' => NULL, 'implement' => $config->entity, 'factory' => array_shift($config->arguments));

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
			$definition->class = NULL;
			$definition->factory = NULL;
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
				$definition->setup = array();
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
			$definition->setInject($config['inject']);
		}

		if (isset($config['run'])) {
			$config['tags']['run'] = (bool) $config['run'];
		}

		if (isset($config['tags'])) {
			Validators::assertField($config, 'tags', 'array');
			if (Config\Helpers::takeParent($config['tags'])) {
				$definition->tags = array();
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
	 * Removes ... recursively.
	 * @return array
	 */
	public static function filterArguments(array $args)
	{
		foreach ($args as $k => $v) {
			if ($v === '...') {
				unset($args[$k]);
			} elseif (is_array($v)) {
				$args[$k] = self::filterArguments($v);
			} elseif ($v instanceof Statement) {
				$tmp = self::filterArguments(array($v->entity));
				$args[$k] = new Statement($tmp[0], self::filterArguments($v->arguments));
			}
		}
		return $args;
	}

}
