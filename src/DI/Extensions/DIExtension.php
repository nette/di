<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Extensions;

use Nette;
use Nette\DI\Definitions\ServiceDefinition;
use Tracy;


/**
 * DI extension.
 */
final class DIExtension extends Nette\DI\CompilerExtension
{
	public array $exportedTags = [];
	public array $exportedTypes = [];
	private bool $debugMode;
	private float $time;


	public function __construct(bool $debugMode = false)
	{
		$this->debugMode = $debugMode;
		$this->time = microtime(true);

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


	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		$builder->addExcludedClasses($this->config->excluded);
	}


	public function beforeCompile(): void
	{
		if ($this->config->lazy && PHP_VERSION_ID >= 80400) {
			$builder = $this->getContainerBuilder();
			foreach ($builder->getDefinitions() as $def) {
				if ($def instanceof ServiceDefinition) {
					$def->lazy ??= true;
				}
			}
		}
	}


	public function afterCompile(Nette\PhpGenerator\ClassType $class): void
	{
		if ($this->config->parentClass) {
			$class->setExtends($this->config->parentClass);
		}

		$this->restrictParameters($class);
		$this->restrictTags($class);
		$this->restrictTypes($class);

		if (
			$this->debugMode &&
			($this->config->debugger ?? $this->getContainerBuilder()->getByType(Tracy\Bar::class))
		) {
			$this->enableTracyIntegration();
		}
	}


	private function restrictParameters(Nette\PhpGenerator\ClassType $class): void
	{
		if (!$this->config->export->parameters) {
			$class->removeMethod('getParameters');
			$class->removeMethod('getStaticParameters');
		}
	}


	private function restrictTags(Nette\PhpGenerator\ClassType $class): void
	{
		$option = $this->config->export->tags;
		if ($option === true) {
		} elseif ($option === false) {
			$class->removeProperty('tags');
		} elseif ($prop = $class->getProperties()['tags'] ?? null) {
			$prop->setValue(array_intersect_key($prop->getValue(), $this->exportedTags + array_flip((array) $option)));
		}
	}


	private function restrictTypes(Nette\PhpGenerator\ClassType $class): void
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
			new Nette\DI\Definitions\Statement(
				'@Tracy\Bar::addPanel',
				[new Nette\DI\Definitions\Statement(Nette\Bridges\DITracy\ContainerPanel::class)],
			),
		]));
	}
}
