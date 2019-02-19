<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Config;

use Nette;
use Nette\Utils\Validators;


/**
 * Configuration file loader.
 */
class Loader
{
	use Nette\SmartObject;

	private const INCLUDES_KEY = 'includes';

	private $adapters = [
		'php' => Adapters\PhpAdapter::class,
		'neon' => Adapters\NeonAdapter::class,
	];

	private $dependencies = [];

	private $loadedFiles = [];


	/**
	 * Reads configuration from file.
	 */
	public function load(string $file, callable $receiver = null): array
	{
		if (!is_file($file) || !is_readable($file)) {
			throw new Nette\FileNotFoundException("File '$file' is missing or is not readable.");
		}

		if (isset($this->loadedFiles[$file])) {
			throw new Nette\InvalidStateException("Recursive included file '$file'");
		}
		$this->loadedFiles[$file] = true;

		$this->dependencies[] = $file;
		$data = $this->getAdapter($file)->load($file);

		$merged = [];
		$receiver = $receiver ?: function (array $data) use (&$merged): void {
			$merged = Helpers::merge($data, $merged); // for back compatibility
		};

		if (isset($data[self::INCLUDES_KEY])) {
			Validators::assert($data[self::INCLUDES_KEY], 'list', "section 'includes' in file '$file'");
			foreach ($data[self::INCLUDES_KEY] as $include) {
				if (!preg_match('#([a-z]+:)?[/\\\\]#Ai', $include)) {
					$include = dirname($file) . '/' . $include;
				}
				$receiver($this->load($include, $receiver), $include);
			}
		}
		unset($data[self::INCLUDES_KEY], $this->loadedFiles[$file]);

		$receiver($data, $file);
		return $merged;
	}


	/**
	 * Save configuration to file.
	 */
	public function save(array $data, string $file): void
	{
		if (file_put_contents($file, $this->getAdapter($file)->dump($data)) === false) {
			throw new Nette\IOException("Cannot write file '$file'.");
		}
	}


	/**
	 * Returns configuration files.
	 */
	public function getDependencies(): array
	{
		return array_unique($this->dependencies);
	}


	/**
	 * Registers adapter for given file extension.
	 * @param  string|Adapter  $adapter
	 * @return static
	 */
	public function addAdapter(string $extension, $adapter)
	{
		$this->adapters[strtolower($extension)] = $adapter;
		return $this;
	}


	private function getAdapter(string $file): Adapter
	{
		$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
		if (!isset($this->adapters[$extension])) {
			throw new Nette\InvalidArgumentException("Unknown file extension '$file'.");
		}
		return is_object($this->adapters[$extension]) ? $this->adapters[$extension] : new $this->adapters[$extension];
	}
}
