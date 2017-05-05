<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\DI;

use Nette,
	Tracy;


/**
 * Initial system DI container generator.
 *
 * @author     David Grudl
 *
 * @property   bool $debugMode
 * @property-write $tempDirectory
 */
class Configurator extends Object
{
	const AUTO = TRUE,
		NONE = FALSE;

	const COOKIE_SECRET = 'nette-debug';

	/** @var callable[]  function(Configurator $sender, Compiler $compiler); Occurs after the compiler is created */
	public $onCompile;

	/** @var array */
	public $defaultExtensions = array(
		'php' => 'Nette\DI\Extensions\PhpExtension',
		'constants' => 'Nette\DI\Extensions\ConstantsExtension',
		'extensions' => 'Nette\DI\Extensions\ExtensionsExtension',
		'decorator' => 'Nette\DI\Extensions\DecoratorExtension',
		'di' => array('Nette\DI\Extensions\DIExtension', array('%debugMode%')),
		'inject' => 'Nette\DI\Extensions\InjectExtension',
	);

	/** @var string[] of classes which shouldn't be autowired */
	public $autowireExcludedClasses = array(
		'stdClass',
	);

	/** @var array */
	protected $parameters;

	/** @var array */
	protected $services = array();

	/** @var array [file|array, section] */
	protected $files = array();


	public function __construct()
	{
		$this->parameters = $this->getDefaultParameters();
	}


	/**
	 * Set parameter %debugMode%.
	 * @param  bool|string|array
	 * @return self
	 */
	public function setDebugMode($value)
	{
		if (is_string($value) || is_array($value)) {
			$value = static::detectDebugMode($value);
		} elseif (!is_bool($value)) {
			throw new Nette\InvalidArgumentException(sprintf('Value must be either a string, array, or boolean, %s given.', gettype($value)));
		}
		$this->parameters['debugMode'] = $value;
		$this->parameters['productionMode'] = !$this->parameters['debugMode']; // compatibility
		$this->parameters['environment'] = $this->parameters['debugMode'] ? 'development' : 'production';
		return $this;
	}


	/**
	 * @return bool
	 */
	public function isDebugMode()
	{
		return $this->parameters['debugMode'];
	}


	/**
	 * Sets path to temporary directory.
	 * @return self
	 */
	public function setTempDirectory($path)
	{
		$this->parameters['tempDir'] = $path;
		return $this;
	}


	/**
	 * Adds new parameters. The %params% will be expanded.
	 * @return self
	 */
	public function addParameters(array $params)
	{
		$this->parameters = Config\Helpers::merge($params, $this->parameters);
		return $this;
	}


	/**
	 * Add instances of services.
	 * @return self
	 */
	public function addServices(array $services)
	{
		$this->services = $services + $this->services;
		return $this;
	}


	/**
	 * @return array
	 */
	protected function getDefaultParameters()
	{
		$trace = debug_backtrace(PHP_VERSION_ID >= 50306 ? DEBUG_BACKTRACE_IGNORE_ARGS : FALSE);
		$last = end($trace);
		$debugMode = static::detectDebugMode();
		return array(
			'appDir' => isset($trace[1]['file']) ? dirname($trace[1]['file']) : NULL,
			'wwwDir' => isset($last['file']) ? dirname($last['file']) : NULL,
			'debugMode' => $debugMode,
			'productionMode' => !$debugMode,
			'environment' => $debugMode ? 'development' : 'production',
			'consoleMode' => PHP_SAPI === 'cli',
			'container' => array(
				'class' => NULL,
				'parent' => 'Nette\DI\Container',
			)
		);
	}


	/**
	 * Adds configuration file.
	 * @return self
	 */
	public function addConfig($file, $section = NULL)
	{
		$this->files[] = array($file, $section === self::AUTO ? $this->parameters['environment'] : $section);
		return $this;
	}


	/**
	 * Returns system DI container.
	 * @return Container
	 */
	public function createContainer()
	{
		$loader = new ContainerLoader(
			$this->getCacheDirectory() . '/Nette.Configurator',
			$this->parameters['debugMode']
		);
		$class = $loader->load(
			array($this->parameters, $this->files),
			array($this, 'generateContainer')
		);

		$container = new $class;
		foreach ($this->services as $name => $service) {
			$container->addService($name, $service);
		}
		$container->initialize();
		return $container;
	}


	/**
	 * @return array [string, array]
	 * @internal
	 */
	public function generateContainer($className)
	{
		$loader = $this->createLoader();
		$config = array();
		$code = '';
		foreach ($this->files as $info) {
			if (is_scalar($info[0])) {
				$code .= "// source: $info[0] $info[1]\n";
				$info[0] = $loader->load($info[0], $info[1]);
			}
			$config = Config\Helpers::merge($info[0], $config);
		}
		$config = Config\Helpers::merge($config, array('parameters' => $this->parameters));

		$compiler = $this->createCompiler();
		$builder = $compiler->getContainerBuilder();
		$builder->addExcludedClasses($this->autowireExcludedClasses);

		foreach ($this->defaultExtensions as $name => $extension) {
			list($class, $args) = is_string($extension) ? array($extension, array()) : $extension;
			if (class_exists($class)) {
				$rc = new \ReflectionClass($class);
				$args = Helpers::expand($args, $config['parameters'], TRUE);
				$compiler->addExtension($name, $args ? $rc->newInstanceArgs($args) : $rc->newInstance());
			}
		}

		$this->onCompile($this, $compiler);

		$code .= $compiler->compile($config, $className, $config['parameters']['container']['parent'])
			. (($parent = $config['parameters']['container']['class']) ? "\nclass $parent extends $className {}\n" : '');

		return array($code, array_merge($loader->getDependencies(), $builder->getDependencies()));
	}


	/**
	 * @return Compiler
	 */
	protected function createCompiler()
	{
		return new Compiler;
	}


	/**
	 * @return DI\Config\Loader
	 */
	protected function createLoader()
	{
		return new Config\Loader;
	}


	protected function getCacheDirectory()
	{
		if (empty($this->parameters['tempDir'])) {
			throw new Nette\InvalidStateException("Set path to temporary directory using setTempDirectory().");
		}
		$dir = $this->parameters['tempDir'] . '/cache';
		if (!is_dir($dir)) {
			@mkdir($dir); // @ - directory may already exist
		}
		return $dir;
	}


	/********************* tools ****************d*g**/


	/**
	 * Detects debug mode by IP address.
	 * @param  string|array  IP addresses or computer names whitelist detection
	 * @return bool
	 */
	public static function detectDebugMode($list = NULL)
	{
		$addr = isset($_SERVER['REMOTE_ADDR'])
			? $_SERVER['REMOTE_ADDR']
			: php_uname('n');
		$secret = isset($_COOKIE[self::COOKIE_SECRET]) && is_string($_COOKIE[self::COOKIE_SECRET])
			? $_COOKIE[self::COOKIE_SECRET]
			: NULL;
		$list = is_string($list)
			? preg_split('#[,\s]+#', $list)
			: (array) $list;
		if (!isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$list[] = '127.0.0.1';
			$list[] = '::1';
		}
		return in_array($addr, $list, TRUE) || in_array("$secret@$addr", $list, TRUE);
	}

}
