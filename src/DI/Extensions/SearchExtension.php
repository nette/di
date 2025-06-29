<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Extensions;

use Nette;
use Nette\Loaders\RobotLoader;
use Nette\Schema\Expect;
use Nette\Utils\Arrays;
use function array_filter, array_keys, array_merge, array_unique, class_exists, count, implode, in_array, interface_exists, is_dir, is_string, method_exists, preg_match, preg_quote, sprintf, str_contains, str_replace, trait_exists;


/**
 * Services auto-discovery.
 */
final class SearchExtension extends Nette\DI\CompilerExtension
{
	private array $classes = [];
	private string $tempDir;


	public function __construct(string $tempDir)
	{
		$this->tempDir = $tempDir;
	}


	public function getConfigSchema(): Nette\Schema\Schema
	{
		return Expect::arrayOf(
			Expect::structure([
				'in' => Expect::string()->required(),
				'files' => Expect::anyOf(Expect::listOf('string'), Expect::string()->castTo('array'))->default([]),
				'classes' => Expect::anyOf(Expect::listOf('string'), Expect::string()->castTo('array'))->default([]),
				'extends' => Expect::anyOf(Expect::listOf('string'), Expect::string()->castTo('array'))->default([]),
				'implements' => Expect::anyOf(Expect::listOf('string'), Expect::string()->castTo('array'))->default([]),
				'exclude' => Expect::structure([
					'files' => Expect::anyOf(Expect::listOf('string'), Expect::string()->castTo('array'))->default([]),
					'classes' => Expect::anyOf(Expect::listOf('string'), Expect::string()->castTo('array'))->default([]),
					'extends' => Expect::anyOf(Expect::listOf('string'), Expect::string()->castTo('array'))->default([]),
					'implements' => Expect::anyOf(Expect::listOf('string'), Expect::string()->castTo('array'))->default([]),
				]),
				'tags' => Expect::array(),
			]),
		)->before(fn($val) => is_string($val['in'] ?? null)
				? ['default' => $val]
				: $val);
	}


	public function loadConfiguration(): void
	{
		foreach (array_filter($this->config) as $name => $batch) {
			if (!is_dir($batch->in)) {
				throw new Nette\DI\InvalidConfigurationException(sprintf(
					"Option '%s\u{a0}›\u{a0}%s\u{a0}›\u{a0}in' must be valid directory name, '%s' given.",
					$this->name,
					$name,
					$batch->in,
				));
			}

			foreach ($this->findClasses($batch) as $class) {
				$this->classes[$class] = array_merge($this->classes[$class] ?? [], $batch->tags);
			}
		}
	}


	public function findClasses(\stdClass $config): array
	{
		$exclude = $config->exclude;
		$robot = new RobotLoader;
		$robot->setTempDirectory($this->tempDir);
		$robot->addDirectory($config->in);
		$robot->acceptFiles = $config->files ?: ['*.php'];
		$robot->ignoreDirs = array_merge($robot->ignoreDirs, $exclude->files);
		$robot->reportParseErrors(false);
		$robot->refresh();
		$classes = array_unique(array_keys($robot->getIndexedClasses()));

		$acceptRE = self::buildNameRegexp($config->classes);
		$rejectRE = self::buildNameRegexp($exclude->classes);
		$acceptParent = array_merge($config->extends, $config->implements);
		$rejectParent = array_merge($exclude->extends, $exclude->implements);

		$found = [];
		foreach ($classes as $class) {
			if (!class_exists($class) && !interface_exists($class) && !trait_exists($class)) {
				throw new Nette\InvalidStateException(sprintf(
					'Class %s was found, but it cannot be loaded by autoloading.',
					$class,
				));
			}

			$rc = new \ReflectionClass($class);
			if (
				($rc->isInstantiable()
					||
					($rc->isInterface()
					&& count($methods = $rc->getMethods()) === 1
					&& in_array($methods[0]->name, ['get', 'create'], true))
				)
				&& (!$acceptRE || preg_match($acceptRE, $rc->name))
				&& (!$rejectRE || !preg_match($rejectRE, $rc->name))
				&& (!$acceptParent || Arrays::some($acceptParent, fn($nm) => $rc->isSubclassOf($nm)))
				&& (!$rejectParent || Arrays::every($rejectParent, fn($nm) => !$rc->isSubclassOf($nm)))
			) {
				$found[] = $rc->name;
			}
		}

		return $found;
	}


	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		foreach ($this->classes as $class => $foo) {
			if ($builder->findByType($class)) {
				unset($this->classes[$class]);
			}
		}

		foreach ($this->classes as $class => $tags) {
			if (class_exists($class)) {
				$def = $builder->addDefinition(null)->setType($class);
			} elseif (method_exists($class, 'create')) {
				$def = $builder->addFactoryDefinition(null)->setImplement($class);
			} else {
				$def = $builder->addAccessorDefinition(null)->setImplement($class);
			}
			$def->setTags(Arrays::normalize($tags, filling: true));
		}
	}


	private static function buildNameRegexp(array $masks): ?string
	{
		$res = [];
		foreach ($masks as $mask) {
			$mask = (str_contains($mask, '\\') ? '' : '**\\') . $mask;
			$mask = preg_quote($mask, '#');
			$mask = str_replace('\*\*\\\\', '(.*\\\)?', $mask);
			$mask = str_replace('\\\\\*\*', '(\\\.*)?', $mask);
			$mask = str_replace('\*', '\w*', $mask);
			$res[] = $mask;
		}

		return $res ? '#^(' . implode('|', $res) . ')$#i' : null;
	}
}
