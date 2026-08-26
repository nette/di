<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Config;

use Nette;
use Nette\Utils\Validators;
use function sprintf;


/**
 * Configuration file loader.
 */
class Loader
{
	private const IncludesKey = 'includes';

	/** @var array<string, class-string<Adapter>|Adapter> */
	private array $adapters = [
		'php' => Adapters\PhpAdapter::class,
		'neon' => Adapters\NeonAdapter::class,
	];

	/** @var string[] */
	private array $dependencies = [];

	/** @var array<string, true> */
	private array $loadedFiles = [];

	/** @var array<string, mixed> */
	private array $parameters = [];


	/**
	 * Reads configuration from file.
	 * @return array<string, mixed>
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
	 * @return string[]
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
	 * @param  class-string<Adapter>|Adapter  $adapter
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

		$adapter = $this->adapters[$extension];
		return $adapter instanceof Adapter ? $adapter : new $adapter;
	}


	/** @param  array<string, mixed>  $params */
	public function setParameters(array $params): static
	{
		$this->parameters = $params;
		return $this;
	}
}
