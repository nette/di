<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI;

use Nette;
use function array_diff_key, array_keys, func_num_args, implode, is_array, is_object, is_string, key, sprintf, str_replace, str_starts_with, substr_replace;


/**
 * Base class for compiler extensions that hook into the DI container compilation process.
 */
abstract class CompilerExtension
{
	protected Compiler $compiler;
	protected string $name;

	/** @var array<string, mixed>|object */
	protected $config = [];

	protected Nette\PhpGenerator\Closure $initialization;


	/** @internal */
	public function setCompiler(Compiler $compiler, string $name): static
	{
		$this->initialization = new Nette\PhpGenerator\Closure;
		$this->compiler = $compiler;
		$this->name = $name;
		return $this;
	}


	/**
	 * Sets the validated configuration for this extension.
	 * @param  array<string, mixed>|object  $config
	 */
	public function setConfig(array|object $config): static
	{
		$this->config = $config;
		return $this;
	}


	/**
	 * Returns extension configuration.
	 * @return array<string, mixed>|object
	 */
	public function getConfig(): array|object
	{
		return $this->config;
	}


	/**
	 * Returns configuration schema.
	 */
	public function getConfigSchema(): Nette\Schema\Schema
	{
		return is_object($this->config)
			? Nette\Schema\Expect::from($this->config)
			: Nette\Schema\Expect::array();
	}


	/**
	 * Checks whether $config contains only $expected items and returns combined array.
	 * @param  array<string, mixed>  $expected
	 * @param  ?array<string, mixed>  $config
	 * @return array<string, mixed>
	 * @throws Nette\InvalidStateException
	 * @deprecated  use getConfigSchema()
	 */
	public function validateConfig(array $expected, ?array $config = null, ?string $name = null): array
	{
		if (func_num_args() === 1) {
			$current = is_array($this->config) ? $this->config : (array) $this->config;
			return $this->config = $this->validateConfig($expected, $current);
		}

		if ($extra = array_diff_key((array) $config, $expected)) {
			$name = $name ? str_replace('.', "\u{a0}›\u{a0}", $name) : $this->name;
			$hint = Nette\Utils\Helpers::getSuggestion(array_keys($expected), key($extra));
			throw new Nette\DI\InvalidConfigurationException(sprintf(
				"Unknown configuration option '%s\u{a0}›\u{a0}%s'",
				$name,
				$hint ? key($extra) : implode("', '{$name}\u{a0}›\u{a0}", array_keys($extra)),
			) . ($hint ? ", did you mean '{$name}\u{a0}›\u{a0}{$hint}'?" : '.'));
		}

		return Nette\Schema\Helpers::merge($config, $expected);
	}


	public function getContainerBuilder(): ContainerBuilder
	{
		return $this->compiler->getContainerBuilder();
	}


	/**
	 * Reads configuration from file.
	 * @return array<string, mixed>
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
	 * @param  array<mixed>  $configList
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
	 * Returns the closure whose body is emitted inside the container's initialize() method.
	 */
	public function getInitialization(): Nette\PhpGenerator\Closure
	{
		return $this->initialization;
	}


	/**
	 * Prepend extension name to identifier or service name.
	 */
	public function prefix(string $id): string
	{
		return substr_replace($id, $this->name . '.', str_starts_with($id, '@') ? 1 : 0, 0);
	}


	/**
	 * Processes extension configuration and registers services. Override in subclasses.
	 * @return void
	 */
	public function loadConfiguration()
	{
	}


	/**
	 * Adjusts the container before compilation. Override in subclasses.
	 * @return void
	 */
	public function beforeCompile()
	{
	}


	/**
	 * Adjusts the generated container class. Override in subclasses.
	 * @return void
	 */
	public function afterCompile(Nette\PhpGenerator\ClassType $class)
	{
	}
}
