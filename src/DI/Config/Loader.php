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

	/** @internal */
	const INCLUDES_KEY = 'includes';

	private $adapters = [
		'php' => Adapters\PhpAdapter::class,
		'ini' => Adapters\IniAdapter::class,
		'neon' => Adapters\NeonAdapter::class,
	];

	private $dependencies = [];


	/**
	 * Reads configuration from file.
	 * @param  string  file name
	 */
	public function load(string $file): array
	{
		if (!is_file($file) || !is_readable($file)) {
			throw new Nette\FileNotFoundException("File '$file' is missing or is not readable.");
		}
		$this->dependencies[] = $file;
		$data = $this->getAdapter($file)->load($file);

		$merged = [];
		if (isset($data[self::INCLUDES_KEY])) {
			Validators::assert($data[self::INCLUDES_KEY], 'list', "section 'includes' in file '$file'");
			foreach ($data[self::INCLUDES_KEY] as $include) {
				if (!preg_match('#([a-z]+:)?[/\\\\]#Ai', $include)) {
					$include = dirname($file) . '/' . $include;
				}
				$merged = Helpers::merge($this->load($include), $merged);
			}
		}
		unset($data[self::INCLUDES_KEY]);

		return Helpers::merge($data, $merged);
	}


	/**
	 * Save configuration to file.
	 * @return void
	 */
	public function save(array $data, string $file)
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
	 * @param  string  file extension
	 * @param  string|IAdapter
	 * @return static
	 */
	public function addAdapter(string $extension, $adapter)
	{
		$this->adapters[strtolower($extension)] = $adapter;
		return $this;
	}


	/** @return IAdapter */
	private function getAdapter(string $file)
	{
		$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
		if (!isset($this->adapters[$extension])) {
			throw new Nette\InvalidArgumentException("Unknown file extension '$file'.");
		}
		return is_object($this->adapters[$extension]) ? $this->adapters[$extension] : new $this->adapters[$extension];
	}
}
