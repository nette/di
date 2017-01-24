<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI;

use Nette;


/**
 * DI container loader.
 */
class ContainerLoader
{
	use Nette\SmartObject;

	/** @var bool */
	private $autoRebuild = FALSE;

	/** @var string */
	private $tempDirectory;


	public function __construct(string $tempDirectory, bool $autoRebuild = FALSE)
	{
		$this->tempDirectory = $tempDirectory;
		$this->autoRebuild = $autoRebuild;
	}


	/**
	 * @param  callable  function (Nette\DI\Compiler $compiler): string|NULL
	 * @param  mixed
	 */
	public function load(callable $generator, $key = NULL): string
	{
		$class = $this->getClassName($key);
		if (!class_exists($class, FALSE)) {
			$this->loadFile($class, $generator);
		}
		return $class;
	}


	public function getClassName($key): string
	{
		return 'Container_' . substr(md5(serialize($key)), 0, 10);
	}


	/**
	 * @return void
	 */
	private function loadFile(string $class, callable $generator)
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
				} elseif (function_exists('opcache_invalidate')) {
					@opcache_invalidate($name, TRUE); // @ can be restricted
				}
			}
		}

		if ((@include $file) === FALSE) { // @ - error escalated to exception
			throw new Nette\IOException("Unable to include '$file'.");
		}
		flock($handle, LOCK_UN);
	}


	private function isExpired(string $file): bool
	{
		if ($this->autoRebuild) {
			$meta = @unserialize((string) file_get_contents("$file.meta")); // @ - file may not exist
			return empty($meta[0]) || DependencyChecker::isExpired(...$meta);
		}
		return FALSE;
	}


	/**
	 * @return array of (code, file[])
	 */
	protected function generate(string $class, callable $generator): array
	{
		$compiler = new Compiler;
		$compiler->setClassName($class);
		$code = $generator(...[&$compiler]) ?: $compiler->compile();
		return [
			"<?php\n$code",
			serialize($compiler->exportDependencies())
		];
	}

}
