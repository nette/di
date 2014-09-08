<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\DI;

use Nette;


/**
 * Configurator compiling extension.
 *
 * @author     David Grudl
 * @property-read array $config
 * @property-read ContainerBuilder $containerBuilder
 */
abstract class CompilerExtension extends Nette\Object
{
	/** @var Compiler */
	protected $compiler;

	/** @var string */
	protected $name;

	/** @var array */
	private $config = array();


	public function setCompiler(Compiler $compiler, $name)
	{
		$this->compiler = $compiler;
		$this->name = $name;
		return $this;
	}


	public function setConfig(array $config)
	{
		$this->config = $config;
		return $this;
	}


	/**
	 * Returns extension configuration.
	 * @param  array default unexpanded values.
	 * @return array
	 */
	public function getConfig(array $defaults = NULL)
	{
		return Config\Helpers::merge($this->config, $this->getContainerBuilder()->expand($defaults));
	}


	/**
	 * @return ContainerBuilder
	 */
	public function getContainerBuilder()
	{
		return $this->compiler->getContainerBuilder();
	}


	/**
	 * Reads configuration from file.
	 * @param  string  file name
	 * @return array
	 */
	public function loadFromFile($file)
	{
		$loader = new Config\Loader;
		$res = $loader->load($file);
		foreach ($loader->getDependencies() as $file) {
			$this->getContainerBuilder()->addDependency($file);
		}
		return $res;
	}


	/**
	 * Prepend extension name to identifier or service name.
	 * @param  string
	 * @return string
	 */
	public function prefix($id)
	{
		return substr_replace($id, $this->name . '.', substr($id, 0, 1) === '@' ? 1 : 0, 0);
	}


	/**
	 * Processes configuration data. Intended to be overridden by descendant.
	 * @return void
	 */
	public function loadConfiguration()
	{
	}


	/**
	 * Adjusts DI container before is compiled to PHP class. Intended to be overridden by descendant.
	 * @return void
	 */
	public function beforeCompile()
	{
	}


	/**
	 * Adjusts DI container compiled to PHP class. Intended to be overridden by descendant.
	 * @return void
	 */
	public function afterCompile(Nette\PhpGenerator\ClassType $class)
	{
	}


	/**
	 * Checks whether $config contains only $expected items.
	 * @return void
	 * @throws Nette\InvalidStateException
	 */
	protected function validateConfig(array $expected, array $config = NULL, $name = NULL)
	{
		if ($extra = array_diff_key((array) $config ?: $this->config, $expected)) {
			$name = $name ?: $this->name;
			$extra = implode(", $name.", array_keys($extra));
			throw new Nette\InvalidStateException("Unknown configuration option $name.$extra.");
		}
	}

}
