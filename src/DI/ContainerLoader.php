<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\DI;

use Nette;


/**
 * DI container loader.
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
	 * @param  callable  function (Nette\DI\Compiler $compiler): string|NULL
	 * @return string
	 */
	public function load($key, $generator)
	{
		$class = $this->getClassName($key);
		if (!class_exists($class, FALSE)) {
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
		if (!$this->isExpired($file) && (@include $file) !== FALSE) { // @ file may not exist
			return;
		}

		if (!is_dir($this->tempDirectory)) {
			@mkdir($this->tempDirectory); // @ - directory may already exist
		}

		$handle = fopen("$file.lock", 'c+');
		if (!$handle || !flock($handle, LOCK_EX)) {
			throw new Nette\IOException("Unable to acquire exclusive lock on '$file.lock'.");
		}

		if (!is_file($file) || $this->isExpired($file)) {
			list($toWrite[$file], $toWrite["$file.meta"]) = $this->generate($class, $generator);

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
			$meta = @unserialize(file_get_contents("$file.meta")); // @ - file may not exist
			$files = $meta ? array_combine($tmp = array_keys($meta), $tmp) : array();
			return $meta !== @array_map('filemtime', $files); // @ - files may not exist
		}
		return FALSE;
	}


	/**
	 * @return array of (code, file[])
	 */
	protected function generate($class, $generator)
	{
		$compiler = new Compiler;
		$compiler->getContainerBuilder()->setClassName($class);
		$code = call_user_func_array($generator, array(& $compiler));
		$code = $code ?: implode("\n\n\n", $compiler->compile());
		$files = $compiler->getDependencies();
		$files = $files ? array_combine($files, $files) : array(); // workaround for PHP 5.3 array_combine
		return array(
			"<?php\n$code",
			serialize(@array_map('filemtime', $files)), // @ - file may not exist
		);
	}

}
