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
use Nette\DI\ServiceCreationException;
use Nette\Utils\Validators;


/**
 * Configuration processor
 */
class Processor
{
	use Nette\SmartObject;

	private $scheme = [
		'fields' => [
			'type' => 'string|Nette\DI\Definitions\Statement',
			'factory' => 'callable|Nette\DI\Definitions\Statement',
			'arguments' => 'array',
			'setup' => 'list',
			'parameters' => 'array',
			'inject' => 'bool',
			'autowired' => 'bool|string|array',
			'tags' => 'array',
			'external' => 'bool',
			'implement' => 'string',
		],
	];

	/** @var Nette\DI\ContainerBuilder */
	private $builder;


	public function __construct(Nette\DI\ContainerBuilder $builder)
	{
		$this->builder = $builder;
	}


	/**
	 * Normalizes and merges configurations.
	 */
	public function merge(array $mainConfig, array $config): array
	{
		if (isset($config['services'])) {
			foreach ($config['services'] as $name => &$def) {
				$def = $this->normalizeStructure($def);
				if (!empty($def['alteration']) && isset($mainConfig['services'][$name])) {
					unset($def['alteration']);
				}
			}
		}
		return Helpers::merge($config, $mainConfig);
	}


	public function normalizeStructure($def): array
	{
		if ($def === null || $def === false) {
			return (array) $def;

		} elseif (is_string($def) && interface_exists($def)) {
			return ['implement' => $def];

		} elseif ($def instanceof Statement && is_string($def->getEntity()) && interface_exists($def->getEntity())) {
			return ['implement' => $def->getEntity(), 'factory' => array_shift($def->arguments)];

		} elseif (!is_array($def) || isset($def[0], $def[1])) {
			return ['factory' => $def];

		} elseif (is_array($def)) {
			foreach (['class' => 'type', 'dynamic' => 'external'] as $alias => $original) {
				if (array_key_exists($alias, $def)) {
					if (array_key_exists($original, $def)) {
						throw new Nette\InvalidStateException("Options '$alias' and '$original' are aliases, use only '$original'.");
					}
					$def[$original] = $def[$alias];
					unset($def[$alias]);
				}
			}
			return $def;

		} else {
			throw new Nette\InvalidStateException('Unexpected format of service definition');
		}
	}


	/**
	 * Adds service normalized definitions from configuration.
	 */
	public function loadDefinitions(array $services): void
	{
		try {
			foreach ($services as $name => $config) {
				$name = $this->createDefinitionName($name, $config);

				if ($config === [false]) {
					$this->builder->removeDefinition($name);
					continue;
				} elseif (!empty($config['alteration']) && !$this->builder->hasDefinition($name)) {
					throw new ServiceCreationException('missing original definition for alteration.');
				}
				unset($config['alteration']);

				$config = $this->prepareConfig($config);
				$definition = $this->retrieveDefinition($name, $config);
				$this->validateConfig($config, $this->scheme['fields']);
				$this->updateDefinition($definition, $config, $name);
			}
		} catch (\Exception $e) {
			throw new ServiceCreationException("Service '$name': " . $e->getMessage(), 0, $e);
		}
	}


	/**
	 * Parses single service definition from configuration.
	 */
	private function updateDefinition(Definitions\ServiceDefinition $definition, array $config, string $name = null): void
	{
		if (array_key_exists('type', $config) || array_key_exists('factory', $config)) {
			$definition->setType(null);
			$definition->setFactory(null);
		}

		if (array_key_exists('type', $config)) {
			if ($config['type'] instanceof Statement) {
				trigger_error("Service '$name': option 'type' or 'class' should be changed to 'factory'.", E_USER_DEPRECATED);
			} else {
				$definition->setType($config['type']);
			}
			$definition->setFactory($config['type']);
		}

		if (array_key_exists('factory', $config)) {
			$definition->setFactory($config['factory']);
		}

		if (array_key_exists('arguments', $config)) {
			$arguments = $config['arguments'];
			if (!Helpers::takeParent($arguments) && !Nette\Utils\Arrays::isList($arguments) && $definition->getFactory()) {
				$arguments += $definition->getFactory()->arguments;
			}
			$definition->setArguments($arguments);
		}

		if (isset($config['setup'])) {
			if (Helpers::takeParent($config['setup'])) {
				$definition->setSetup([]);
			}
			foreach ($config['setup'] as $id => $setup) {
				Validators::assert($setup, 'callable|Nette\DI\Definitions\Statement|array:1', "setup item #$id");
				if (is_array($setup)) {
					$setup = new Statement(key($setup), array_values($setup));
				}
				$definition->addSetup($setup);
			}
		}

		if (isset($config['parameters'])) {
			$definition->setParameters($config['parameters']);
		}

		if (isset($config['implement'])) {
			$definition->setImplement($config['implement']);
			$definition->setAutowired(true);
		}

		if (isset($config['autowired'])) {
			$definition->setAutowired($config['autowired']);
		}

		if (isset($config['external'])) {
			$definition->setDynamic($config['external']);
		}

		if (isset($config['inject'])) {
			$definition->addTag(Extensions\InjectExtension::TAG_INJECT, $config['inject']);
		}

		if (isset($config['tags'])) {
			if (Helpers::takeParent($config['tags'])) {
				$definition->setTags([]);
			}
			foreach ($config['tags'] as $tag => $attrs) {
				if (is_int($tag) && is_string($attrs)) {
					$definition->addTag($attrs);
				} else {
					$definition->addTag($tag, $attrs);
				}
			}
		}
	}


	private function validateFields(array $config, array $fields): void
	{
		$expected = array_keys($fields);
		if ($error = array_diff(array_keys($config), $expected)) {
			$hints = array_filter(array_map(function ($error) use ($expected) {
				return Nette\Utils\ObjectHelpers::getSuggestion($expected, $error);
			}, $error));
			$hint = $hints ? ", did you mean '" . implode("', '", $hints) . "'?" : '.';
			throw new Nette\InvalidStateException(sprintf("Unknown key '%s' in definition of service$hint", implode("', '", $error)));
		}

		foreach ($fields as $field => $expected) {
			if (isset($config[$field])) {
				Validators::assertField($config, $field, $expected);
			}
		}
	}


	/**
	 * Replaces @extension with and prepends service names with namespace.
	 */
	public function applyNamespace(array $services, string $namespace): array
	{
		foreach ($services as $name => $def) {
			$def = Nette\DI\Helpers::prefixServiceName($def, $namespace);
			if (is_string($name)) {
				unset($services[$name]);
				$name = $namespace . '.' . $name;
			}
			$services[$name] = $def;
		}
		return $services;
	}


	private function createDefinitionName($name, array $config): string
	{
		if (is_int($name)) {
			$factory = $config['factory'] ?? null;
			$postfix = $factory instanceof Statement && is_string($factory->getEntity()) ? '.' . $factory->getEntity(
				) : (is_scalar($factory) ? ".$factory" : '');
			$name = (count($this->builder->getDefinitions()) + 1) . preg_replace('#\W+#', '_', $postfix);
		} elseif (preg_match('#^@[\w\\\\]+\z#', $name)) {
			$name = $this->builder->getByType(substr($name, 1), true);
		}
		return $name;
	}


	private function prepareConfig(array $config): array
	{
		$params = $this->builder->parameters;
		if (isset($config['parameters'])) {
			foreach ((array) $config['parameters'] as $k => $v) {
				$v = explode(' ', is_int($k) ? $v : $k);
				$params[end($v)] = $this->builder::literal('$' . end($v));
			}
		}
		$config = Nette\DI\Helpers::expand($config, $params);
		$config = Nette\DI\Helpers::filterArguments($config);
		return $config;
	}


	private function retrieveDefinition(string $name, array &$config): Definitions\ServiceDefinition
	{
		if (Helpers::takeParent($config)) {
			$this->builder->removeDefinition($name);
		}
		return $this->builder->hasDefinition($name)
			? $this->builder->getDefinition($name)
			: $this->builder->addDefinition($name);
	}
}
