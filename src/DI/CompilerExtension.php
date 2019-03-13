<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI;

use Nette;


/**
 * Configurator compiling extension.
 */
abstract class CompilerExtension
{
	use Nette\SmartObject;

	/** @var Compiler */
	protected $compiler;

	/** @var string */
	protected $name;

	/** @var array */
	protected $config = [];


	/**
	 * @return static
	 */
	public function setCompiler(Compiler $compiler, string $name)
	{
		$this->compiler = $compiler;
		$this->name = $name;
		return $this;
	}


	/**
	 * @return static
	 */
	public function setConfig(array $config)
	{
		$this->config = $config;
		return $this;
	}


	/**
	 * Returns extension configuration.
	 */
	public function getConfig(): array
	{
		return $this->config;
	}


	/**
	 * Checks whether $config contains only $expected items and returns combined array.
	 * @throws Nette\InvalidStateException
	 */
	public function validateConfig(array $expected, array $config = null, string $name = null): array
	{
		if (func_num_args() === 1) {
			return $this->config = $this->validateConfig($expected, $this->config);
		}
		if ($extra = array_diff_key((array) $config, $expected)) {
			$name = $name ? str_replace('.', ' › ', $name) : $this->name;
			$hint = Nette\Utils\ObjectHelpers::getSuggestion(array_keys($expected), key($extra));
			$extra = $hint ? key($extra) : implode("', '{$name} › ", array_keys($extra));
			throw new Nette\DI\InvalidConfigurationException("Unknown configuration option '{$name} › {$extra}'" . ($hint ? ", did you mean '{$name} › {$hint}'?" : '.'));
		}
		return Config\Helpers::merge($config, $expected);
	}


	public function getContainerBuilder(): ContainerBuilder
	{
		return $this->compiler->getContainerBuilder();
	}


	/**
	 * Reads configuration from file.
	 */
	public function loadFromFile(string $file): array
	{
		$loader = $this->createLoader();
		$res = $loader->load($file);
		$this->compiler->addDependencies($loader->getDependencies());
		return $res;
	}


	/**
	 * Loads list of service definitions from configuration.
	 * Prefixes its names and replaces @extension with name in definition.
	 */
	public function loadDefinitionsFromConfig(array $configList): void
	{
		$res = [];
		foreach ($configList as $key => $config) {
			$key = is_string($key) ? $this->name . '.' . $key : $key;
			$res[$key] = Helpers::prefixServiceName($config, $this->name);
		}
		$this->compiler->loadDefinitionsFromConfig($res);
	}


	protected function createLoader(): Config\Loader
	{
		return new Config\Loader;
	}


	/**
	 * Prepend extension name to identifier or service name.
	 */
	public function prefix(string $id): string
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
}
