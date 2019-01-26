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
 * Processor for configuration of service definitions.
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
	 * Normalizes and merges configuration of list of service definitions. Left has higher priority.
	 */
	public function mergeConfigs(array $left, ?array $right): array
	{
		foreach ($left as $key => &$def) {
			$def = $this->normalizeConfig($def);
			if (!empty($def['alteration']) && isset($right[$key])) {
				unset($def['alteration']);
			}
		}
		return Helpers::merge($left, $right);
	}


	/**
	 * Normalizes configuration of service definition.
	 */
	public function normalizeConfig($config): array
	{
		if ($config === null || $config === false) {
			return (array) $config;

		} elseif (is_string($config) && interface_exists($config)) {
			return ['implement' => $config];

		} elseif ($config instanceof Statement && is_string($config->getEntity()) && interface_exists($config->getEntity())) {
			$res = ['implement' => $config->getEntity()];
			if (array_keys($config->arguments) === ['tagged']) {
				$res += $config->arguments;
			} elseif (count($config->arguments) > 1) {
				$res['references'] = $config->arguments;
			} else {
				$res['factory'] = array_shift($config->arguments);
			}
			return $res;

		} elseif (!is_array($config) || isset($config[0], $config[1])) {
			return ['factory' => $config];

		} elseif (is_array($config)) {
			foreach (['class' => 'type', 'dynamic' => 'imported'] as $alias => $original) {
				if (array_key_exists($alias, $config)) {
					if (array_key_exists($original, $config)) {
						throw new Nette\InvalidStateException("Options '$alias' and '$original' are aliases, use only '$original'.");
					}
					$config[$original] = $config[$alias];
					unset($config[$alias]);
				}
			}
			return $config;

		} else {
			throw new Nette\InvalidStateException('Unexpected format of service definition');
		}
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
	private function loadDefinition(?string $name, array $config): void
	{
		try {
			if ($config === [false]) {
				$this->builder->removeDefinition($name);
				return;
			} elseif (!empty($config['alteration']) && !$this->builder->hasDefinition($name)) {
				throw new ServiceCreationException('missing original definition for alteration.');
			}
			unset($config['alteration']);

			$config = $this->expandParameters($config);
			$def = $this->retrieveDefinition($name, $config);
			$scheme = $this->schemes[get_class($def)];
			$this->validateFields($config, $scheme['fields']);
			$this->{$scheme['method']}($def, $config, $name);
			$this->updateDefinition($def, $config);
		} catch (\Exception $e) {
			throw new ServiceCreationException(($name ? "Service '$name': " : '') . $e->getMessage(), 0, $e);
		}
	}


	/**
	 * Updates service definition according to normalized configuration.
	 */
	private function updateServiceDefinition(Definitions\ServiceDefinition $definition, array $config, string $name = null): void
	{
		$config = self::processArguments($config);

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
		$config = self::processArguments($config);

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
			if (!Helpers::takeParent($arguments) && !Nette\Utils\Arrays::isList($arguments) && $resultDef->getFactory()) {
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


	private function retrieveDefinition(?string $name, array &$config): Definitions\Definition
	{
		if (Helpers::takeParent($config)) {
			$this->builder->removeDefinition($name);
		}

		if ($name && $this->builder->hasDefinition($name)) {
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
