<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Extensions;

use Nette;
use Nette\DI\Config\Expect;
use Nette\Loaders\RobotLoader;
use Nette\Utils\Arrays;


/**
 * Services auto-discovery.
 */
final class SearchExtension extends Nette\DI\CompilerExtension
{
	/** @var array */
	private $classes = [];

	/** @var string */
	private $tempDir;


	public function __construct(string $tempDir)
	{
		$this->tempDir = $tempDir;
	}


	public function getConfigSchema(): Nette\DI\Config\Schema
	{
		return Expect::arrayOf(
			Expect::structure([
				'in' => Expect::string()->required(),
				'files' => Expect::enum(Expect::listOf('string'), Expect::string()->castTo('array')),
				'classes' => Expect::enum(Expect::listOf('string'), Expect::string()->castTo('array')),
				'extends' => Expect::enum(Expect::listOf('string'), Expect::string()->castTo('array')),
				'implements' => Expect::enum(Expect::listOf('string'), Expect::string()->castTo('array')),
				'exclude' => Expect::structure([
					'classes' => Expect::enum(Expect::listOf('string'), Expect::string()->castTo('array')),
					'extends' => Expect::enum(Expect::listOf('string'), Expect::string()->castTo('array')),
					'implements' => Expect::enum(Expect::listOf('string'), Expect::string()->castTo('array')),
				]),
				'tags' => Expect::array(),
			])
		)->normalize(function ($val) {
			return is_string($val['in'] ?? null)
				? ['default' => $val]
				: $val;
		});
	}


	public function loadConfiguration()
	{
		foreach (array_filter($this->config) as $name => $batch) {
			if (!is_dir($batch->in)) {
				throw new Nette\DI\InvalidConfigurationException("Option '{$this->name} › {$name} › in' must be valid directory name, '{$batch->in}' given.");
			}

			foreach ($this->findClasses($batch) as $class) {
				$this->classes[$class] = array_merge($this->classes[$class] ?? [], $batch->tags);
			}
		}
	}


	public function findClasses(\stdClass $config): array
	{
		$robot = new RobotLoader;
		$robot->setTempDirectory($this->tempDir);
		$robot->addDirectory($config->in);
		$robot->acceptFiles = $config->files ?: ['*.php'];
		$robot->reportParseErrors(false);
		$robot->refresh();
		$classes = array_unique(array_keys($robot->getIndexedClasses()));
		$classes = array_filter($classes, 'class_exists');

		$exclude = $config->exclude;
		$acceptRE = self::buildNameRegexp($config->classes);
		$rejectRE = self::buildNameRegexp($exclude->classes);
		$acceptParent = array_merge($config->extends, $config->implements);
		$rejectParent = array_merge($exclude->extends, $exclude->implements);

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
