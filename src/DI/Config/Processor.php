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

	private $schemes = [
		Definitions\ServiceDefinition::class => [
			'method' => 'updateServiceDefinition',
			'fields' => [
				'type' => 'string|Nette\DI\Definitions\Statement',
				'factory' => 'callable|Nette\DI\Definitions\Statement',
				'arguments' => 'array',
				'setup' => 'list',
				'inject' => 'bool',
				'autowired' => 'bool|string|array',
				'tags' => 'array',
			],
		],
		Definitions\AccessorDefinition::class => [
			'method' => 'updateAccessorDefinition',
			'fields' => [
				'type' => 'string',
				'implement' => 'string',
				'factory' => 'callable|Nette\DI\Definitions\Statement',
				'autowired' => 'bool|string|array',
				'tags' => 'array',
			],
		],
		Definitions\FactoryDefinition::class => [
			'method' => 'updateFactoryDefinition',
			'fields' => [
				'type' => 'string',
				'factory' => 'callable|Nette\DI\Definitions\Statement',
				'implement' => 'string',
				'arguments' => 'array',
				'setup' => 'list',
				'parameters' => 'array',
				'references' => 'array',
				'tagged' => 'string',
				'autowired' => 'bool|string|array',
				'tags' => 'array',
			],
		],
		Definitions\LocatorDefinition::class => [
			'method' => 'updateLocatorDefinition',
			'fields' => [
				'implement' => 'string',
				'references' => 'array',
				'tagged' => 'string',
				'autowired' => 'bool|string|array',
				'tags' => 'array',
			],
		],
		Definitions\ImportedDefinition::class => [
			'method' => 'updateImportedDefinition',
			'fields' => [
				'type' => 'string',
				'imported' => 'bool',
				'autowired' => 'bool|string|array',
				'tags' => 'array',
			],
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
			$res = ['implement' => $def->getEntity()];
			if (array_keys($def->arguments) === ['tagged']) {
				$res += $def->arguments;
			} elseif (count($def->arguments) > 1) {
				$res['references'] = $def->arguments;
			} else {
				$res['factory'] = array_shift($def->arguments);
			}
			return $res;

		} elseif (!is_array($def) || isset($def[0], $def[1])) {
			return ['factory' => $def];

		} elseif (is_array($def)) {
			foreach (['class' => 'type', 'dynamic' => 'imported'] as $alias => $original) {
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
				$def = $this->retrieveDefinition($name, $config);
				$scheme = $this->schemes[get_class($def)];
				$this->validateFields($config, $scheme['fields']);
				$this->{$scheme['method']}($def, $config, $name);
				$this->updateDefinition($def, $config);
			}
		} catch (\Exception $e) {
			throw new ServiceCreationException("Service '$name': " . $e->getMessage(), 0, $e);
		}
	}


	/**
	 * Parses single service definition from configuration.
	 */
	private function updateServiceDefinition(Definitions\ServiceDefinition $definition, array $config, string $name = null): void
	{
		$config = self::filterArguments($config);

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

		if (isset($config['inject'])) {
			$definition->addTag(Extensions\InjectExtension::TAG_INJECT, $config['inject']);
		}
	}


	private function updateAccessorDefinition(Definitions\AccessorDefinition $definition, array $config): void
	{
		if (isset($config['implement'])) {
			$definition->setImplement($config['implement']);
		}

		if ($ref = $config['factory'] ?? $config['type'] ?? null) {
			$definition->setReference($ref);
		}
	}


	private function updateFactoryDefinition(Definitions\FactoryDefinition $definition, array $config): void
	{
		$config = self::filterArguments($config);

		$resultDef = $definition->getResultDefinition();

		if (isset($config['implement'])) {
			$definition->setImplement($config['implement']);
			$definition->setAutowired(true);
		}

		if (array_key_exists('factory', $config)) {
			$resultDef->setFactory($config['factory']);
		}

		if (array_key_exists('type', $config)) {
			$resultDef->setFactory($config['type']);
		}

		if (array_key_exists('arguments', $config)) {
			$arguments = $config['arguments'];
			if (!Helpers::takeParent($arguments) && !Nette\Utils\Arrays::isList($arguments) && $definition->getFactory()) {
				$arguments += $resultDef->getFactory()->arguments;
			}
			$resultDef->setArguments($arguments);
		}

		if (isset($config['setup'])) {
			if (Helpers::takeParent($config['setup'])) {
				$resultDef->setSetup([]);
			}
			foreach ($config['setup'] as $id => $setup) {
				Validators::assert($setup, 'callable|Nette\DI\Definitions\Statement|array:1', "setup item #$id");
				if (is_array($setup)) {
					$setup = new Statement(key($setup), array_values($setup));
				}
				$resultDef->addSetup($setup);
			}
		}

		if (isset($config['parameters'])) {
			$definition->setParameters($config['parameters']);
		}
	}


	private function updateLocatorDefinition(Definitions\LocatorDefinition $definition, array $config): void
	{
		if (isset($config['implement'])) {
			$definition->setImplement($config['implement']);
		}

		if (isset($config['references'])) {
			$definition->setReferences($config['references']);
		}

		if (isset($config['tagged'])) {
			$definition->setTagged($config['tagged']);
		}
	}


	private function updateImportedDefinition(Definitions\ImportedDefinition $definition, array $config): void
	{
		if (array_key_exists('type', $config)) {
			$definition->setType($config['type']);
		}
	}


	private function updateDefinition(Definitions\Definition $definition, array $config): void
	{
		if (isset($config['autowired'])) {
			$definition->setAutowired($config['autowired']);
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
		return $config;
	}


	private function retrieveDefinition(string $name, array &$config): Definitions\Definition
	{
		if (Helpers::takeParent($config)) {
			$this->builder->removeDefinition($name);
		}

		if ($this->builder->hasDefinition($name)) {
			return $this->builder->getDefinition($name);

		} elseif (isset($config['implement'], $config['references']) || isset($config['implement'], $config['tagged'])) {
			return $this->builder->addLocatorDefinition($name);

		} elseif (isset($config['implement'])) {
			return method_exists($config['implement'], 'create')
				? $this->builder->addFactoryDefinition($name)
				: $this->builder->addAccessorDefinition($name);

		} elseif (isset($config['imported'])) {
			return $this->builder->addImportedDefinition($name);

		} else {
			return $this->builder->addDefinition($name);
		}
	}


	/**
	 * Removes ... and process constants recursively.
	 */
	public static function filterArguments(array $args): array
	{
		foreach ($args as $k => $v) {
			if ($v === '...') {
				unset($args[$k]);
			} elseif (is_string($v) && preg_match('#^[\w\\\\]*::[A-Z][A-Z0-9_]*\z#', $v, $m)) {
				$args[$k] = constant(ltrim($v, ':'));
			} elseif (is_string($v) && preg_match('#^@[\w\\\\]+\z#', $v)) {
				$args[$k] = new Definitions\Reference(substr($v, 1));
			} elseif (is_array($v)) {
				$args[$k] = self::filterArguments($v);
			} elseif ($v instanceof Statement) {
				$tmp = self::filterArguments([$v->getEntity()]);
				$args[$k] = new Statement($tmp[0], self::filterArguments($v->arguments));
			}
		}
		return $args;
	}
}
