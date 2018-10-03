<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Extensions;

use Nette;
use Nette\Loaders\RobotLoader;
use Nette\Utils\Arrays;


/**
 * Services auto-discovery.
 */
final class SearchExtension extends Nette\DI\CompilerExtension
{
	/** @var array */
	private $defaults = [
		'in' => null,
		'files' => [],
		'classes' => [],
		'extends' => [],
		'implements' => [],
		'exclude' => [
			'classes' => [],
			'extends' => [],
			'implements' => [],
		],
		'tags' => [],
	];

	/** @var array */
	private $classes = [];

	/** @var string */
	private $tempDir;


	public function __construct(string $tempDir)
	{
		$this->tempDir = $tempDir . '/Nette.SearchExtension';
	}


	public function loadConfiguration()
	{
		$batches = is_string($this->config['in'] ?? null)
			? ['default' => $this->config]
			: $this->config;

		foreach (array_filter($batches) as $name => $batch) {
			$batch = $this->validateConfig($this->defaults, $batch, $this->prefix((string) $name));
			$batch['exclude'] = $this->validateConfig($this->defaults['exclude'], $batch['exclude'], $this->prefix("$name.exclude"));

			$in = $batch['in'];
			if (!is_string($in) || !is_dir($in)) {
				throw new Nette\InvalidStateException("Option '{$this->name} › {$name} › in' must be valid directory name, " . (is_string($in) ? "'$in'" : gettype($in)) . ' given.');
			}

			foreach ($this->findClasses($batch) as $class) {
				$this->classes[$class] = array_merge($this->classes[$class] ?? [], $batch['tags']);
			}
		}
	}


	public function findClasses(array $config): array
	{
		$robot = new RobotLoader;
		$robot->setTempDirectory($this->tempDir);
		$robot->addDirectory($config['in']);
		$robot->acceptFiles = (array) ($config['files'] ?: '*.php');
		$robot->reportParseErrors(false);
		$robot->refresh();
		$classes = array_unique(array_keys($robot->getIndexedClasses()));
		$classes = array_filter($classes, 'class_exists');

		$exclude = $config['exclude'];
		$acceptRE = self::buildNameRegexp((array) $config['classes']);
		$rejectRE = self::buildNameRegexp((array) $exclude['classes']);
		$acceptParent = array_merge((array) $config['extends'], (array) $config['implements']);
		$rejectParent = array_merge((array) $exclude['extends'], (array) $exclude['implements']);

		$found = [];
		foreach ($classes as $class) {
			$rc = new \ReflectionClass($class);
			if (
				$rc->isInstantiable()
				&& (!$acceptRE || preg_match($acceptRE, $rc->getName()))
				&& (!$rejectRE || !preg_match($rejectRE, $rc->getName()))
				&& (!$acceptParent || Arrays::some($acceptParent, function ($nm) use ($rc) { return $rc->isSubclassOf($nm); }))
				&& (!$rejectParent || Arrays::every($rejectParent, function ($nm) use ($rc) { return !$rc->isSubclassOf($nm); }))
			) {
				$found[] = $rc->getName();
			}
		}
		return $found;
	}


	public function beforeCompile()
	{
		$builder = $this->getContainerBuilder();

		foreach ($this->classes as $class => $tags) {
			if (!$builder->findByType($class)) {
				$builder->addDefinition(null)
					->setType($class)
					->setTags(Arrays::normalize($tags, true));
			}
		}
	}


	private static function buildNameRegexp(array $masks): ?string
	{
		$res = [];
		foreach ((array) $masks as $mask) {
			$mask = (strpos($mask, '\\') === false ? '**\\' : '') . $mask;
			$mask = preg_quote($mask, '#');
			$mask = str_replace('\*\*\\\\', '(.*\\\\)?', $mask);
			$mask = str_replace('\\\\\*\*', '(\\\\.*)?', $mask);
			$mask = str_replace('\*', '\w*', $mask);
			$res[] = $mask;
		}
		return $res ? '#^(' . implode('|', $res) . ')$#i' : null;
	}
}
