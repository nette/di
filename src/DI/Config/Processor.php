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
	 * Normalizes and merges configuration of list of service definitions.
	 */
	public function processSchema(array $configs): array
	{
		$schema = Expect::arrayOf(new DefinitionSchema);
		$config = (array) $schema->flatten($configs, ['services']);
		foreach ($config as &$def) {
			$def = $this->expandParameters($def);
		}
		return $schema->complete($config, ['services']);
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

			$this->{"update{$config->defType}Definition"}($def, $config);
			$this->updateDefinition($def, $config);
		} catch (\Exception $e) {
			throw new Nette\DI\InvalidConfigurationException(($name ? "Service '$name': " : '') . $e->getMessage(), [], $e);
		}
	}


	/**
	 * Updates service definition according to normalized configuration.
	 */
	private function updateServiceDefinition(Definitions\ServiceDefinition $definition, \stdClass $config): void
	{
		if ($config->factory) {
			$definition->setFactory(self::processArguments([$config->factory])[0]);
			$definition->setType(null);
		}

		if ($config->type) {
			$definition->setType($config->type);
		}

		if ($config->arguments) {
			$arguments = self::processArguments($config->arguments);
			if (empty($config->reset['arguments']) && !Nette\Utils\Arrays::isList($arguments)) {
				$arguments += $definition->getFactory()->arguments;
			}
			$definition->setArguments($arguments);
		}

		if (isset($config->setup)) {
			if (!empty($config->reset['setup'])) {
				$definition->setSetup([]);
			}
			foreach (self::processArguments($config->setup) as $id => $setup) {
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
			$resultDef->setFactory(self::processArguments([$config->factory])[0]);
		}

		if ($config->type) {
			$resultDef->setFactory($config->type);
		}

		if ($config->arguments) {
			$arguments = self::processArguments($config->arguments);
			if (empty($config->reset['arguments']) && !Nette\Utils\Arrays::isList($arguments)) {
				$arguments += $resultDef->getFactory()->arguments;
			}
			$resultDef->setArguments($arguments);
		}

		if (isset($config->setup)) {
			if (!empty($config->reset['setup'])) {
				$resultDef->setSetup([]);
			}
			foreach (self::processArguments($config->setup) as $id => $setup) {
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


	private function expandParameters(array $config): array
	{
		$params = $this->builder->parameters;
		if (isset($config['parameters'])) {
			foreach ((array) $config['parameters'] as $k => $v) {
				$v = explode(' ', is_int($k) ? $v : $k);
				$params[end($v)] = $this->builder::literal('$' . end($v));
			}
		}
		$config = Nette\DI\Helpers::expand($config, $params);
		return $config;
	}


	private function retrieveDefinition(?string $name, \stdClass $config): Definitions\Definition
	{
		if (!empty($config->reset['all'])) {
			$this->builder->removeDefinition($name);
		}

		if ($name && $this->builder->hasDefinition($name)) {
			return $this->builder->getDefinition($name);

		} elseif ($config->defType === 'service') {
			return $this->builder->addDefinition($name);
		} else {
			return $this->builder->{"add{$config->defType}Definition"}($name);
		}
	}


	/**
	 * Removes ... and resolves constants in arguments recursively.
	 */
	public static function processArguments(array $args): array
	{
		foreach ($args as $k => $v) {
			if ($v === '...') {
				unset($args[$k]);
			} elseif (is_string($v) && preg_match('#^[\w\\\\]*::[A-Z][A-Z0-9_]*\z#', $v, $m)) {
				$args[$k] = constant(ltrim($v, ':'));
			} elseif (is_string($v) && preg_match('#^@[\w\\\\]+\z#', $v)) {
				$args[$k] = new Definitions\Reference(substr($v, 1));
			} elseif (is_array($v)) {
				$args[$k] = self::processArguments($v);
			} elseif ($v instanceof Statement) {
				$tmp = self::processArguments([$v->getEntity()]);
				$args[$k] = new Statement($tmp[0], self::processArguments($v->arguments));
			}
		}
		return $args;
	}
}
