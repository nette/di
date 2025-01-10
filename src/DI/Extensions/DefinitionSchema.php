<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Extensions;

use Nette;
use Nette\DI\Config\Helpers;
use Nette\DI\Definitions;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Context;
use Nette\Schema\Expect;
use Nette\Schema\Schema;


/**
 * Service configuration schema.
 */
class DefinitionSchema implements Schema
{
	private Nette\DI\ContainerBuilder $builder;


	public function __construct(Nette\DI\ContainerBuilder $builder)
	{
		$this->builder = $builder;
	}


	public function complete($def, Context $context)
	{
		if ($def === [false]) {
			return (object) $def;
		}

		if (Helpers::takeParent($def)) {
			$def['reset']['all'] = true;
		}

		foreach (['arguments', 'setup', 'tags'] as $k) {
			if (isset($def[$k]) && Helpers::takeParent($def[$k])) {
				$def['reset'][$k] = true;
			}
		}

		$type = $this->sniffType(end($context->path), $def);
		$def = $this->getSchema($type)->complete($def, $context);
		if ($def) {
			$def->defType = $type;
		}

		return $def;
	}


	public function merge($def, $base)
	{
		if (!empty($def['alteration'])) {
			unset($def['alteration']);
		}

		return Nette\Schema\Helpers::merge($def, $base);
	}


	/**
	 * Normalizes configuration of service definitions.
	 */
	public function normalize($def, Context $context)
	{
		if ($def === null || $def === false) {
			return (array) $def;

		} elseif (is_string($def) && interface_exists($def)) {
			return ['implement' => $def];

		} elseif ($def instanceof Statement && is_string($def->getEntity()) && interface_exists($def->getEntity())) {
			$res = ['implement' => $def->getEntity()];
			if (array_keys($def->arguments) === ['tagged']) {
				$res += $def->arguments;
			} elseif (array_keys($def->arguments) === [0]) {
				$res['create'] = $def->arguments[0];
			} elseif ($def->arguments) {
				$res['references'] = $def->arguments;
			}

			return $res;

		} elseif (!is_array($def) || isset($def[0], $def[1])) {
			return ['create' => $def];

		} else {
			// back compatibility
			if (isset($def['factory']) && !isset($def['create'])) {
				$def['create'] = $def['factory'];
				unset($def['factory']);
			}
			if (isset($def['class']) && !isset($def['type']) && !isset($def['imported'])) {
				$def[isset($def['create']) ? 'type' : 'create'] = $def['class'];
				unset($def['class']);
			}

			return $def;
		}
	}


	public function completeDefault(Context $context)
	{
	}


	private function sniffType($key, array $def): string
	{
		if (is_string($key)) {
			$name = preg_match('#^@[\w\\\\]+$#D', $key)
				? $this->builder->getByType(substr($key, 1))
				: $key;

			if ($name && $this->builder->hasDefinition($name)) {
				return get_class($this->builder->getDefinition($name));
			}
		}

		if (isset($def['implement'], $def['references']) || isset($def['implement'], $def['tagged'])) {
			return Definitions\LocatorDefinition::class;

		} elseif (isset($def['implement'])) {
			return method_exists($def['implement'], 'create')
				? Definitions\FactoryDefinition::class
				: Definitions\AccessorDefinition::class;

		} elseif (isset($def['imported'])) {
			return Definitions\ImportedDefinition::class;

		} elseif (!$def) {
			throw new Nette\DI\InvalidConfigurationException("Service '$key': Empty definition.");

		} else {
			return Definitions\ServiceDefinition::class;
		}
	}


	private static function getSchema(string $type): Schema
	{
		static $cache;
		$cache = $cache ?: [
			Definitions\ServiceDefinition::class => self::getServiceSchema(),
			Definitions\AccessorDefinition::class => self::getAccessorSchema(),
			Definitions\FactoryDefinition::class => self::getFactorySchema(),
			Definitions\LocatorDefinition::class => self::getLocatorSchema(),
			Definitions\ImportedDefinition::class => self::getImportedSchema(),
		];
		return $cache[$type];
	}


	private static function getServiceSchema(): Schema
	{
		return Expect::structure([
			'type' => Expect::type('string'),
			'create' => Expect::type('callable|Nette\DI\Definitions\Statement|Nette\DI\Definitions\Reference'),
			'arguments' => Expect::array(),
			'setup' => Expect::listOf('callable|Nette\DI\Definitions\Statement|array:1'),
			'inject' => Expect::bool(),
			'autowired' => Expect::type('bool|string|array'),
			'tags' => Expect::array(),
			'reset' => Expect::array(),
			'alteration' => Expect::bool(),
			'lazy' => Expect::bool(),
		]);
	}


	private static function getAccessorSchema(): Schema
	{
		return Expect::structure([
			'type' => Expect::string(),
			'implement' => Expect::string(),
			'create' => Expect::type('callable|Nette\DI\Definitions\Statement'),
			'autowired' => Expect::type('bool|string|array'),
			'tags' => Expect::array(),
		]);
	}


	private static function getFactorySchema(): Schema
	{
		return Expect::structure([
			'type' => Expect::string(),
			'create' => Expect::type('callable|Nette\DI\Definitions\Statement'),
			'implement' => Expect::string(),
			'arguments' => Expect::array(),
			'setup' => Expect::listOf('callable|Nette\DI\Definitions\Statement|array:1'),
			'references' => Expect::array(),
			'tagged' => Expect::string(),
			'inject' => Expect::bool(),
			'autowired' => Expect::type('bool|string|array'),
			'tags' => Expect::array(),
			'reset' => Expect::array(),
		]);
	}


	private static function getLocatorSchema(): Schema
	{
		return Expect::structure([
			'implement' => Expect::string(),
			'references' => Expect::array(),
			'tagged' => Expect::string(),
			'autowired' => Expect::type('bool|string|array'),
			'tags' => Expect::array(),
		]);
	}


	private static function getImportedSchema(): Schema
	{
		return Expect::structure([
			'type' => Expect::string(),
			'imported' => Expect::bool(),
			'autowired' => Expect::type('bool|string|array'),
			'tags' => Expect::array(),
		]);
	}
}
