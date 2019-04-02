<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Config;

use Nette;
use Nette\DI\Definitions;
use Nette\DI\Definitions\Statement;
use Nette\DI\Extensions;


/**
 * Processor for configuration of service definitions.
 */
class Processor
{
	use Nette\SmartObject;

	/** @var Nette\DI\ContainerBuilder */
	private $builder;


	public function __construct(Nette\DI\ContainerBuilder $builder)
	{
		$this->builder = $builder;
	}


	/**
	 * Loads list of service definitions from normalized configuration.
	 */
	public function loadDefinitions(array $configList): void
	{
		foreach ($configList as $key => $config) {
			$this->loadDefinition($this->convertKeyToName($key), $config);
		}
	}


	/**
	 * Loads service definition from normalized configuration.
	 */
	private function loadDefinition(?string $name, \stdClass $config): void
	{
		try {
			if ((array) $config === [false]) {
				$this->builder->removeDefinition($name);
				return;
			} elseif (!empty($config->alteration) && !$this->builder->hasDefinition($name)) {
				throw new Nette\DI\InvalidConfigurationException('missing original definition for alteration.');
			}

			$def = $this->retrieveDefinition($name, $config);

			static $methods = [
				Definitions\ServiceDefinition::class => 'updateServiceDefinition',
				Definitions\AccessorDefinition::class => 'updateAccessorDefinition',
				Definitions\FactoryDefinition::class => 'updateFactoryDefinition',
				Definitions\LocatorDefinition::class => 'updateLocatorDefinition',
				Definitions\ImportedDefinition::class => 'updateImportedDefinition',
			];
			$this->{$methods[$config->defType]}($def, $config);
			$this->updateDefinition($def, $config);
		} catch (\Exception $e) {
			throw new Nette\DI\InvalidConfigurationException(($name ? "Service '$name': " : '') . $e->getMessage(), 0, $e);
		}
	}


	/**
	 * Updates service definition according to normalized configuration.
	 */
	private function updateServiceDefinition(Definitions\ServiceDefinition $definition, \stdClass $config): void
	{
		if ($config->factory) {
			$definition->setFactory(Nette\DI\Helpers::filterArguments([$config->factory])[0]);
			$definition->setType(null);
		}

		if ($config->type) {
			$definition->setType($config->type);
		}

		if ($config->arguments) {
			$arguments = Nette\DI\Helpers::filterArguments($config->arguments);
			if (empty($config->reset['arguments']) && !Nette\Utils\Arrays::isList($arguments)) {
				$arguments += $definition->getFactory()->arguments;
			}
			$definition->setArguments($arguments);
		}

		if (isset($config->setup)) {
			if (!empty($config->reset['setup'])) {
				$definition->setSetup([]);
			}
			foreach (Nette\DI\Helpers::filterArguments($config->setup) as $id => $setup) {
				if (is_array($setup)) {
					$setup = new Statement(key($setup), array_values($setup));
				}
				$definition->addSetup($setup);
			}
		}

		if (isset($config->inject)) {
			$definition->addTag(Extensions\InjectExtension::TAG_INJECT, $config->inject);
		}
	}


	private function updateAccessorDefinition(Definitions\AccessorDefinition $definition, \stdClass $config): void
	{
		if (isset($config->implement)) {
			$definition->setImplement($config->implement);
		}

		if ($ref = $config->factory ?? $config->type ?? null) {
			$definition->setReference($ref);
		}
	}


	private function updateFactoryDefinition(Definitions\FactoryDefinition $definition, \stdClass $config): void
	{
		$resultDef = $definition->getResultDefinition();

		if (isset($config->implement)) {
			$definition->setImplement($config->implement);
			$definition->setAutowired(true);
		}

		if ($config->factory) {
			$resultDef->setFactory(Nette\DI\Helpers::filterArguments([$config->factory])[0]);
		}

		if ($config->type) {
			$resultDef->setFactory($config->type);
		}

		if ($config->arguments) {
			$arguments = Nette\DI\Helpers::filterArguments($config->arguments);
			if (empty($config->reset['arguments']) && !Nette\Utils\Arrays::isList($arguments)) {
				$arguments += $resultDef->getFactory()->arguments;
			}
			$resultDef->setArguments($arguments);
		}

		if (isset($config->setup)) {
			if (!empty($config->reset['setup'])) {
				$resultDef->setSetup([]);
			}
			foreach (Nette\DI\Helpers::filterArguments($config->setup) as $id => $setup) {
				if (is_array($setup)) {
					$setup = new Statement(key($setup), array_values($setup));
				}
				$resultDef->addSetup($setup);
			}
		}

		if (isset($config->parameters)) {
			$definition->setParameters($config->parameters);
		}

		if (isset($config->inject)) {
			$definition->addTag(Extensions\InjectExtension::TAG_INJECT, $config->inject);
		}
	}


	private function updateLocatorDefinition(Definitions\LocatorDefinition $definition, \stdClass $config): void
	{
		if (isset($config->implement)) {
			$definition->setImplement($config->implement);
		}

		if (isset($config->references)) {
			$definition->setReferences($config->references);
		}

		if (isset($config->tagged)) {
			$definition->setTagged($config->tagged);
		}
	}


	private function updateImportedDefinition(Definitions\ImportedDefinition $definition, \stdClass $config): void
	{
		if ($config->type) {
			$definition->setType($config->type);
		}
	}


	private function updateDefinition(Definitions\Definition $definition, \stdClass $config): void
	{
		if (isset($config->autowired)) {
			$definition->setAutowired($config->autowired);
		}

		if (isset($config->tags)) {
			if (!empty($config->reset['tags'])) {
				$definition->setTags([]);
			}
			foreach ($config->tags as $tag => $attrs) {
				if (is_int($tag) && is_string($attrs)) {
					$definition->addTag($attrs);
				} else {
					$definition->addTag($tag, $attrs);
				}
			}
		}
	}


	private function convertKeyToName($key): ?string
	{
		if (is_int($key)) {
			return null;
		} elseif (preg_match('#^@[\w\\\\]+\z#', $key)) {
			return $this->builder->getByType(substr($key, 1), true);
		}
		return $key;
	}


	private function retrieveDefinition(?string $name, \stdClass $config): Definitions\Definition
	{
		if (!empty($config->reset['all'])) {
			$this->builder->removeDefinition($name);
		}

		return $name && $this->builder->hasDefinition($name)
			? $this->builder->getDefinition($name)
			: $this->builder->addDefinition($name, new $config->defType);
	}
}
