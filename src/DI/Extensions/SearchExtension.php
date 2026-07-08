<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Extensions;

use Nette;
use Nette\DI;
use Nette\DI\Attributes\Hook;
use Nette\DI\ContainerBuilder;
use Nette\DI\Phase;
use Nette\Loaders\RobotLoader;
use Nette\Schema\Expect;
use Nette\Utils\Arrays;
use function count, in_array, is_string, sprintf;


/**
 * Services auto-discovery.
 */
final class SearchExtension extends Nette\DI\CompilerExtension
{
	/** @var array<string, object{in: string, files: list<string>, classes: list<string>, extends: list<string>, implements: list<string>, exclude: object{files: list<string>, classes: list<string>, extends: list<string>, implements: list<string>}, tags: array<string, mixed>}> */
	protected $config = [];

	/** @var array<string, array<string, mixed>> */
	private array $classes = [];


	public function __construct(
		private readonly string $tempDir,
	) {
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


	#[Hook(Phase::Register)]
	public function doScanDirectories(ContainerBuilder $builder): void
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


	#[Hook(Phase::Discover)]
	public function doDiscoverServices(ContainerBuilder $builder): void
	{
		foreach ($this->classes as $class => $foo) {
			if ($builder->has(type: $class)) {
				unset($this->classes[$class]);
			}
		}

		foreach ($this->classes as $class => $tags) {
			$def = class_exists($class)
				? $builder->add(null, $class)
				: $builder->add(null, method_exists($class, 'create') ? di\factory($class) : di\accessor($class));
			$def->setTags(Arrays::normalize($tags, filling: true));
		}
	}


	/**
	 * Finds classes matching the given search configuration batch.
	 * @return string[]
	 */
	public function findClasses(\stdClass $config): array
	{
		$exclude = $config->exclude;
		$robot = new RobotLoader;
		$robot->setTempDirectory($this->tempDir);
		$robot->addDirectory($config->in);
		$robot->acceptFiles = $config->files ?: ['*.php'];
		$robot->ignoreDirs = array_values(array_merge($robot->ignoreDirs, $exclude->files));
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
					&& in_array($methods[0]->name, ['get', 'create'], strict: true))
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


	/** @param  string[]  $masks */
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
