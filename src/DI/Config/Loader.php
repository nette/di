<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Config;

use Nette;
use Nette\Utils\Validators;
use function array_unique, dirname, is_file, is_object, is_readable, pathinfo, preg_match, sprintf, strtolower;
use const PATHINFO_EXTENSION;


/**
 * Configuration file loader.
 */
class Loader
{
	private const IncludesKey = 'includes';

	private array $adapters = [
		'php' => Adapters\PhpAdapter::class,
		'neon' => Adapters\NeonAdapter::class,
	];
	private array $dependencies = [];
	private array $loadedFiles = [];
	private array $parameters = [];


	/**
	 * Reads configuration from file.
	 */
	public function load(string $file, ?bool $merge = true): array
	{
		if (!is_file($file) || !is_readable($file)) {
			throw new Nette\FileNotFoundException(sprintf("File '%s' is missing or is not readable.", $file));
		}

		if (isset($this->loadedFiles[$file])) {
			throw new Nette\InvalidStateException(sprintf("Recursive included file '%s'", $file));
		}

		$this->loadedFiles[$file] = true;

		$this->dependencies[] = $file;
		$data = $this->getAdapter($file)->load($file);

		$res = [];
		if (isset($data[self::IncludesKey])) {
			Validators::assert($data[self::IncludesKey], 'list', "section 'includes' in file '$file'");
			$includes = Nette\DI\Helpers::expand($data[self::IncludesKey], $this->parameters);
			foreach ($includes as $include) {
				$include = $this->expandIncludedFile($include, $file);
				$res = Nette\Schema\Helpers::merge($this->load($include, $merge), $res);
			}
		}

		unset($data[self::IncludesKey], $this->loadedFiles[$file]);

		if ($merge === false) {
			$res[] = $data;
		} else {
			$res = Nette\Schema\Helpers::merge($data, $res);
		}

		return $res;
	}


	/**
	 * Returns configuration files.
	 */
	public function getDependencies(): array
	{
		return array_unique($this->dependencies);
	}


	/**
	 * Expands included file name.
	 */
	public function expandIncludedFile(string $includedFile, string $mainFile): string
	{
		return preg_match('#([a-z]+:)?[/\\\]#Ai', $includedFile) // is absolute
			? $includedFile
			: dirname($mainFile) . '/' . $includedFile;
	}


	/**
	 * Registers adapter for given file extension.
	 */
	public function addAdapter(string $extension, string|Adapter $adapter): static
	{
		$this->adapters[strtolower($extension)] = $adapter;
		return $this;
	}


	private function getAdapter(string $file): Adapter
	{
		$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
		if (!isset($this->adapters[$extension])) {
			throw new Nette\InvalidArgumentException(sprintf("Unknown file extension '%s'.", $file));
		}

		return is_object($this->adapters[$extension])
			? $this->adapters[$extension]
			: new $this->adapters[$extension];
	}


	public function setParameters(array $params): static
	{
		$this->parameters = $params;
		return $this;
	}
}
