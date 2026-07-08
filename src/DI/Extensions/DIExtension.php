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
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Phase;
use Nette\PhpGenerator\ClassType;
use Tracy;
use function is_array;
use const PHP_VERSION_ID;


/**
 * DI extension.
 */
final class DIExtension extends Nette\DI\CompilerExtension
{
	/** @var array<string, true> */
	public array $exportedTags = [];

	/** @var array<string, true> */
	public array $exportedTypes = [];

	/** @var object{debugger: ?bool, excluded: list<class-string>, parentClass: ?string, export: object{parameters: bool, tags: list<string>|bool|null, types: list<string>|bool|null}, lazy: bool} */
	protected $config = [];
	private bool $debugMode;
	private float $time;


	public function __construct(bool $debugMode = false)
	{
		$this->debugMode = $debugMode;
		$this->time = microtime(as_float: true);

		$this->config = new class {
			public ?bool $debugger = null;

			/** @var string[] */
			public array $excluded = [];
			public ?string $parentClass = null;
			public object $export;
			public bool $lazy = false;
		};
		$this->config->export = new class {
			public bool $parameters = true;

			/** @var string[]|bool|null */
			public array|bool|null $tags = true;

			/** @var string[]|bool|null */
			public array|bool|null $types = true;
		};
	}


	#[Hook(Phase::Register)]
	public function doRegisterExcludedClasses(ContainerBuilder $builder): void
	{
		$builder->addExcludedClasses($this->config->excluded);
	}


	#[Hook(Phase::Modify)]
	public function doEnableLazyProxies(ContainerBuilder $builder): void
	{
		if ($this->config->lazy && PHP_VERSION_ID >= 80400) {
			foreach ($builder->getAll() as $def) {
				if ($def instanceof ServiceDefinition && $def->isLazy() === null) {
					$def->lazy();
				}
			}
		}
	}


	#[Hook(Phase::Compile, after: ParametersExtension::class)]
	public function doConfigureContainerClass(ClassType $class): void
	{
		if ($this->config->parentClass) {
			$class->setExtends($this->config->parentClass);
		}

		$this->restrictParameters($class);
		$this->restrictTags($class);
		$this->restrictTypes($class);

		if (
			$this->debugMode &&
			($this->config->debugger ?? $this->getContainerBuilder()->has(type: Tracy\Bar::class))
		) {
			$this->enableTracyIntegration();
		}
	}


	private function restrictParameters(ClassType $class): void
	{
		if (!$this->config->export->parameters) {
			$class->removeMethod('getParameters');
			$class->removeMethod('getStaticParameters');
		}
	}


	private function restrictTags(ClassType $class): void
	{
		$option = $this->config->export->tags;
		if ($option === true) {
		} elseif ($option === false) {
			$class->removeProperty('tags');
		} elseif ($prop = $class->getProperties()['tags'] ?? null) {
			$prop->setValue(array_intersect_key($prop->getValue(), $this->exportedTags + array_flip((array) $option)));
		}
	}


	private function restrictTypes(ClassType $class): void
	{
		$option = $this->config->export->types;
		if ($option === true) {
			return;
		}

		$prop = $class->getProperty('wiring');
		$prop->setValue(array_intersect_key(
			$prop->getValue(),
			$this->exportedTypes + (is_array($option) ? array_flip($option) : []),
		));
	}


	private function enableTracyIntegration(): void
	{
		Nette\Bridges\DITracy\ContainerPanel::$compilationTime = $this->time;
		$this->initialization->addBody($this->getContainerBuilder()->formatPhp('?;', [
			di\wire(Tracy\Bar::class)->method('addPanel', [di\create(Nette\Bridges\DITracy\ContainerPanel::class)]),
		]));
	}
}
