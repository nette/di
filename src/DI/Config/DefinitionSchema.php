<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Config;

use Nette;
use Nette\DI\Definitions\Statement;


/**
 * Service configuration schema.
 */
class DefinitionSchema implements Schema
{
	use Nette\SmartObject;

	public function flatten(array $defs, array $path = [])
	{
		foreach ($defs as $i => &$def) {
			$def = $this->normalize($def, end($path), $i === 0);
		}

		$schema = self::getSchema($this->sniffType($defs[0]));
		return $schema->flatten($defs, $path);
	}


	public function complete($def, array $path = [])
	{
		if ($def === [false]) {
			return (object) $def;
		}
		$type = $this->sniffType($def);
		$def = $this->getSchema($type)->complete($def, $path);
		$def->defType = $type;
		return $def;
	}


	public function getDefault(array $path)
	{
	}


	/**
	 * Normalizes configuration of service definitions.
	 */
	private function normalize($def, $key = null, bool $first = null): array
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
			} elseif ($factory = array_shift($def->arguments)) {
				$res['factory'] = $factory;
			}
			return $res;

		} elseif (!is_array($def) || isset($def[0], $def[1])) {
			return ['factory' => $def];

		} elseif (is_array($def)) {
			if (isset($def['class']) && !isset($def['type'])) {
				if ($def['class'] instanceof Statement) {
					trigger_error("Service '$key': option 'class' should be changed to 'factory'.", E_USER_DEPRECATED);
					$def['factory'] = $def['class'];
					unset($def['class']);
				} elseif (!isset($def['factory'])) {
					$def['factory'] = $def['class'];
					unset($def['class']);
				}
			}

			foreach (['class' => 'type', 'dynamic' => 'imported'] as $alias => $original) {
				if (array_key_exists($alias, $def)) {
					if (array_key_exists($original, $def)) {
						throw new Nette\DI\InvalidConfigurationException("Options '$alias' and '$original' are aliases, use only '$original'.");
					}
					$def[$original] = $def[$alias];
					unset($def[$alias]);
				}
			}

			if ($first) {
				if (Helpers::takeParent($def)) {
					$def['reset']['all'] = true;
				}
				foreach (['arguments', 'setup', 'tags'] as $k) {
					if (isset($def[$k]) && Helpers::takeParent($def[$k])) {
						$def['reset'][$k] = true;
					}
				}
			} elseif (!empty($def['alteration'])) {
				unset($def['alteration']);
			}

			return $def;

		} else {
			throw new Nette\DI\InvalidConfigurationException('Unexpected format of service definition');
		}
	}


	private function sniffType(array $def)
	{
		if (isset($def['implement'], $def['references']) || isset($def['implement'], $def['tagged'])) {
			return 'locator';

		} elseif (isset($def['implement'])) {
			return method_exists($def['implement'], 'create')
				? 'factory'
				: 'accessor';

		} elseif (isset($def['imported'])) {
			return 'imported';

		} else {
			return 'service';
		}
	}


	private static function getSchema(string $type): Schema
	{
		static $cache;
		return $cache[$type] ?? ($cache[$type] = self::{"get{$type}Schema"}());
	}


	private static function getServiceSchema(): Schema
	{
		return Expect::structure([
			'type' => Expect::type('string'),
			'factory' => Expect::type('callable|Nette\DI\Definitions\Statement'),
			'arguments' => Expect::array(),
			'setup' => Expect::listOf('callable|Nette\DI\Definitions\Statement|array:1'),
			'inject' => Expect::bool(),
			'autowired' => Expect::type('bool|string|array'),
			'tags' => Expect::array(),
			'reset' => Expect::array(),
			'alteration' => Expect::bool(),
		]);
	}


	private static function getAccessorSchema(): Schema
	{
		return Expect::structure([
			'type' => Expect::string(),
			'implement' => Expect::string(),
			'factory' => Expect::type('callable|Nette\DI\Definitions\Statement'),
			'autowired' => Expect::type('bool|string|array'),
			'tags' => Expect::array(),
		]);
	}


	private static function getFactorySchema(): Schema
	{
		return Expect::structure([
			'type' => Expect::string(),
			'factory' => Expect::type('callable|Nette\DI\Definitions\Statement'),
			'implement' => Expect::string(),
			'arguments' => Expect::array(),
			'setup' => Expect::listOf('callable|Nette\DI\Definitions\Statement|array:1'),
			'parameters' => Expect::array(),
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
