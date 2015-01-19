<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\DI;

use Nette;


/**
 * DI container loader.
 *
 * @author     David Grudl
 */
class ContainerLoader extends Nette\Object
{
	/** @var bool */
	private $autoRebuild = FALSE;

	/** @var string */
	private $tempDirectory;


	public function __construct($tempDirectory, $autoRebuild = FALSE)
	{
		$this->tempDirectory = $tempDirectory;
		$this->autoRebuild = $autoRebuild;
	}


	/**
	 * @param  mixed
	 * @param  callable  function(string $class): [code, files]
	 * @return string
	 */
	public function load($key, $generator)
	{
		$class = $this->getClassName($key);
		if (!class_exists($class)) {
			$this->loadFile($class, $generator);
		}
		return $class;
	}


	/**
	 * @return string
	 */
	public function getClassName($key)
	{
		return 'Container_' . substr(md5(serialize($key)), 0, 10);
	}


	/**
	 * @return void
	 */
	private function loadFile($class, $generator)
	{
		$file = "$this->tempDirectory/$class.php";
		if (!$this->autoRebuild && (@include $file) !== FALSE) { // @ - file may not exist
			return;
		}

		if (!is_dir($this->tempDirectory)) {
			@mkdir($this->tempDirectory); // @ - directory may already exist
		}
		$handle = fopen("$file.lock", 'c+');
		if (!$handle) {
			throw new Nette\IOException("Unable to open or create file '$file.lock'.");
		}

		if ($this->autoRebuild) {
			flock($handle, LOCK_SH);
			foreach ((array) @unserialize(file_get_contents("$file.meta")) as $f => $time) { // @ - file may not exist
				if (@filemtime($f) !== $time) { // @ - stat may fail
					@unlink($file); // @ - file may not exist
					break;
				}
			}
		}

		if (!is_file($file)) {
			flock($handle, LOCK_EX);
			if (!is_file($file)) {
				list($code, $dependencies) = call_user_func($generator, $class);
				$code = "<?php\n" . $code;
				if (file_put_contents("$file.tmp", $code) !== strlen($code) || !rename("$file.tmp", $file)) {
					@unlink("$file.tmp"); // @ - file may not be created
					throw new Nette\IOException("Unable to create file '$file'.");
				}
				$tmp = array();
				foreach ((array) $dependencies as $f) {
					$tmp[$f] = @filemtime($f); // @ - stat may fail
				}
				file_put_contents("$file.meta", serialize($tmp));
			}
		}

		require $file;
	}

}
