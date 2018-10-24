<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Config;

use Nette;
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
		return Helpers::merge($config, $mainConfig);
	}


	/**
	 * Adds service definitions from configuration.
	 */
	public function loadDefinitions(array $services): void
	{
		foreach ($services as $name => $def) {
			if (is_int($name)) {
				$postfix = $def instanceof Statement && is_string($def->getEntity()) ? '.' . $def->getEntity() : (is_scalar($def) ? ".$def" : '');
				$name = (count($this->builder->getDefinitions()) + 1) . preg_replace('#\W+#', '_', $postfix);
			} elseif (preg_match('#^@[\w\\\\]+\z#', $name)) {
				$name = $this->builder->getByType(substr($name, 1), true);
			}

			if ($def === false) {
				$this->builder->removeDefinition($name);
				continue;
			}

			$params = $this->builder->parameters;
			if (is_array($def) && isset($def['parameters'])) {
				foreach ((array) $def['parameters'] as $k => $v) {
					$v = explode(' ', is_int($k) ? $v : $k);
					$params[end($v)] = $this->builder::literal('$' . end($v));
				}
			}
			$def = Nette\DI\Helpers::expand($def, $params);

			if (is_array($def) && !empty($def['alteration']) && !$this->builder->hasDefinition($name)) {
				throw new ServiceCreationException("Service '$name': missing original definition for alteration.");
			}
			if (Helpers::takeParent($def)) {
				$this->builder->removeDefinition($name);
			}
			$definition = $this->builder->hasDefinition($name)
				? $this->builder->getDefinition($name)
				: $this->builder->addDefinition($name);

			try {
				$this->updateDefinition($definition, $def, $name);
			} catch (\Exception $e) {
				throw new ServiceCreationException("Service '$name': " . $e->getMessage(), 0, $e);
			}
		}
	}


	/**
	 * Parses single service definition from configuration.
	 */
	public function updateDefinition(Nette\DI\Definitions\ServiceDefinition $definition, $config, string $name = null): void
	{
		if ($config === null) {
			return;

		} elseif (is_string($config) && interface_exists($config)) {
			$config = ['implement' => $config];

		} elseif ($config instanceof Statement && is_string($config->getEntity()) && interface_exists($config->getEntity())) {
			$config = ['implement' => $config->getEntity(), 'factory' => array_shift($config->arguments)];

		} elseif (!is_array($config) || isset($config[0], $config[1])) {
			$config = ['factory' => $config];
		}

		$known = ['type', 'class', 'factory', 'arguments', 'setup', 'autowired', 'dynamic', 'external', 'inject', 'parameters', 'implement', 'run', 'tags', 'alteration'];
		if ($error = array_diff(array_keys($config), $known)) {
			$hints = array_filter(array_map(function ($error) use ($known) {
				return Nette\Utils\ObjectHelpers::getSuggestion($known, $error);
			}, $error));
			$hint = $hints ? ", did you mean '" . implode("', '", $hints) . "'?" : '.';
			throw new Nette\InvalidStateException(sprintf("Unknown key '%s' in definition of service$hint", implode("', '", $error)));
		}

		$config = Nette\DI\Helpers::filterArguments($config);

		if (array_key_exists('class', $config) || array_key_exists('factory', $config)) {
			$definition->setType(null);
			$definition->setFactory(null);
		}

		if (array_key_exists('type', $config)) {
			Validators::assertField($config, 'type', 'string|null');
			$definition->setType($config['type']);
			if (array_key_exists('class', $config)) {
				throw new Nette\InvalidStateException("Unexpected 'class' when 'type' is used.");
			}
		}

		if (array_key_exists('class', $config)) {
			Validators::assertField($config, 'class', 'string|Nette\DI\Definitions\Statement|null');
			if ($config['class'] instanceof Statement) {
				trigger_error("Service '$name': option 'class' should be changed to 'factory'.", E_USER_DEPRECATED);
			} else {
				$definition->setType($config['class']);
			}
			$definition->setFactory($config['class']);
		}

		if (array_key_exists('factory', $config)) {
			Validators::assertField($config, 'factory', 'callable|Nette\DI\Definitions\Statement|null');
			$definition->setFactory($config['factory']);
		}

		if (array_key_exists('arguments', $config)) {
			Validators::assertField($config, 'arguments', 'array');
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
			Validators::assertField($config, 'setup', 'list');
			foreach ($config['setup'] as $id => $setup) {
				Validators::assert($setup, 'callable|Nette\DI\Definitions\Statement|array:1', "setup item #$id");
				if (is_array($setup)) {
					$setup = new Statement(key($setup), array_values($setup));
				}
				$definition->addSetup($setup);
			}
		}

		if (isset($config['parameters'])) {
			Validators::assertField($config, 'parameters', 'array');
			$definition->setParameters($config['parameters']);
		}

		if (isset($config['implement'])) {
			Validators::assertField($config, 'implement', 'string');
			$definition->setImplement($config['implement']);
			$definition->setAutowired(true);
		}

		if (isset($config['autowired'])) {
			Validators::assertField($config, 'autowired', 'bool|string|array');
			$definition->setAutowired($config['autowired']);
		}

		if (isset($config['external'])) {
			$config['dynamic'] = $config['external'];
		}

		if (isset($config['dynamic'])) {
			Validators::assertField($config, 'dynamic', 'bool');
			$definition->setDynamic($config['dynamic']);
		}

		if (isset($config['inject'])) {
			Validators::assertField($config, 'inject', 'bool');
			$definition->addTag(Extensions\InjectExtension::TAG_INJECT, $config['inject']);
		}

		if (isset($config['tags'])) {
			Validators::assertField($config, 'tags', 'array');
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
}
