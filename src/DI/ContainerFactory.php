<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\DI;

use Nette;


/**
 * DI container generator.
 *
 * @author     David Grudl
 */
class ContainerFactory extends Nette\Object
{
	/** @var array of function(ContainerFactory $factory, Compiler $compiler, $config); Occurs after the compiler is created */
	public $onCompile;

	/** @var bool */
	public $autoRebuild = FALSE;

	/** @var string */
	public $class = 'SystemContainer';

	/** @var string */
	public $parentClass = 'Nette\DI\Container';

	/** @var array */
	public $config = array();

	/** @var array [file|array, section] */
	public $configFiles = array();

	/** @var string */
	public $tempDirectory;

	/** @var array */
	private $dependencies = array();


	public function __construct($tempDirectory)
	{
		$this->tempDirectory = $tempDirectory;
	}


	/**
	 * @return Container
	 */
	public function create()
	{
		if (!class_exists($this->class)) {
			$this->loadClass();
		}
		return new $this->class;
	}


	/**
	 * @return string
	 */
	protected function generateCode()
	{
		$compiler = $this->createCompiler();
		$config = $this->generateConfig();
		$this->onCompile($this, $compiler, $config);

		$code = "<?php\n";
		foreach ($this->configFiles as $info) {
			if (is_scalar($info[0])) {
				$code .= "// source: $info[0] $info[1]\n";
			}
		}
		$code .= "\n" . $compiler->compile($config, $this->class, $this->parentClass);

		if ($this->autoRebuild !== 'compat') { // back compatibility
			$this->dependencies = array_merge($this->dependencies, $compiler->getContainerBuilder()->getDependencies());
		}
		return $code;
	}


	/**
	 * @return array
	 */
	protected function generateConfig()
	{
		$config = array();
		$loader = $this->createLoader();
		foreach ($this->configFiles as $info) {
			$info = is_scalar($info[0]) ? $loader->load($info[0], $info[1]) : $info[0];
			$config = Config\Helpers::merge($info, $config);
		}
		$this->dependencies = array_merge($this->dependencies, $loader->getDependencies());

		return Config\Helpers::merge($config, $this->config);
	}


	/**
	 * @return void
	 */
	private function loadClass()
	{
		$key = md5(serialize(array($this->config, $this->configFiles, $this->class, $this->parentClass)));
		$file = "$this->tempDirectory/$key.php";

		if (!$this->autoRebuild && (@include $file) !== FALSE) { // @ - file may not exist
			return;
		}

		$handle = fopen("$file.tmp", 'c+');
		if (!$handle) {
			throw new Nette\IOException("Unable to open or create file '$file.tmp'.");
		}

		if ($this->autoRebuild) {
			flock($handle, LOCK_SH);
			foreach ((array) @unserialize(file_get_contents("$file.meta")) as $f => $time) { // @ - file may not exist
				if (@filemtime($f) !== $time) { // @ - stat may fail
					unlink($file);
					break;
				}
			}
		}

		if (!is_file($file)) {
			flock($handle, LOCK_EX);
			if (!is_file($file)) {
				$this->dependencies = array();
				$code = $this->generateCode();
				if (!file_put_contents($file, $code)) {
					throw new Nette\IOException("Unable to write file '$file'.");
				}
				$tmp = array();
				foreach ($this->dependencies as $f) {
					$tmp[$f] = @filemtime($f); // @ - stat may fail
				}
				file_put_contents("$file.meta", serialize($tmp));
			}
		}

		require $file;
	}


	/**
	 * @return Compiler
	 */
	protected function createCompiler()
	{
		return new Compiler;
	}


	/**
	 * @return Config\Loader
	 */
	protected function createLoader()
	{
		return new Config\Loader;
	}

}
