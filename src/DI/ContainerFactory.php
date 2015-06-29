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
 * @deprecated
 */
class ContainerFactory extends Nette\Object
{
	/** @var callable[]  function (ContainerFactory $factory, Compiler $compiler, $config); Occurs after the compiler is created */
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
		trigger_error(__CLASS__ . ' is deprecated; use ContainerLoader.', E_USER_DEPRECATED);
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
		if (!$this->isExpired($file) && (@include $file) !== FALSE) {
			return;
		}

		$handle = fopen("$file.lock", 'c+');
		if (!$handle || !flock($handle, LOCK_EX)) {
			throw new Nette\IOException("Unable to acquire exclusive lock on '$file.lock'.");
		}

		if (!is_file($file) || $this->isExpired($file)) {
			$this->dependencies = array();
			$toWrite[$file] = $this->generateCode();
			$files = $this->dependencies ? array_combine($this->dependencies, $this->dependencies) : array();
			$toWrite["$file.meta"] = serialize(@array_map('filemtime', $files)); // @ - file may not exist

			foreach ($toWrite as $name => $content) {
				if (file_put_contents("$name.tmp", $content) !== strlen($content) || !rename("$name.tmp", $name)) {
					@unlink("$name.tmp"); // @ - file may not exist
					throw new Nette\IOException("Unable to create file '$name'.");
				}
			}
		}

		if ((@include $file) === FALSE) { // @ - error escalated to exception
			throw new Nette\IOException("Unable to include '$file'.");
		}
		flock($handle, LOCK_UN);
	}


	private function isExpired($file)
	{
		if ($this->autoRebuild) {
			$meta = @unserialize(file_get_contents("$file.meta")); // @ - files may not exist
			$files = $meta ? array_combine($tmp = array_keys($meta), $tmp) : array();
			return $meta !== @array_map('filemtime', $files); // @ - files may not exist
		}
		return FALSE;
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
