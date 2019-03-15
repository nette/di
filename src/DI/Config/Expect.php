<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Config;

use Nette;
use Nette\DI\InvalidConfigurationException;


/**
 * Default implementation of Schema.
 *
 * @method static self scalar($default = null)
 * @method static self string($default = null)
 * @method static self int($default = null)
 * @method static self float($default = null)
 * @method static self bool($default = null)
 * @method static self null()
 * @method static self array()
 * @method static self list()
 * @method static self mixed()
 * @method static self class($default = null)
 * @method static self interface($default = null)
 * @method static self email($default = null)
 */
final class Expect implements Schema
{
	use Nette\SmartObject;

	private const
		TYPE_STRUCTURE = 'structure',
		TYPE_ENUM = 'enum';

	/** @var string */
	private $type;

	/** @var bool */
	private $required = false;

	/** @var mixed */
	private $default;

	/** @var array|null  for structure|enum */
	private $items;

	/** @var Schema|null  for array|list */
	private $otherItems;

	/** @var array */
	private $range = [null, null];

	/** @var callable|null */
	private $normalize;

	/** @var callable[] */
	private $asserts = [];

	/** @var string|null */
	private $castTo;


	public static function __callStatic(string $name, array $args): self
	{
		$me = new self($name);
		$me->default = $args[0] ?? $me->default;
		return $me;
	}


	public static function type(string $type): self
	{
		return new self($type);
	}


	public static function enum(...$set): self
	{
		$me = new self(self::TYPE_ENUM);
		$me->items = $set;
		foreach ($set as $item) {
			if ($item instanceof self && !$item->required) {
				$me->default = $item->default;
				break;
			}
		}
		return $me;
	}


	public static function structure(array $items): self
	{
		$me = new self(self::TYPE_STRUCTURE);
		$me->items = $items;
		$me->castTo = 'object';
		return $me;
	}


	/**
	 * @param  string|Schema  $type
	 */
	public static function arrayOf($type): self
	{
		return (new self('array'))->otherItems($type);
	}


	/**
	 * @param  string|Schema  $type
	 */
	public static function listOf($type): self
	{
		return (new self('list'))->otherItems($type);
	}


	/**
	 * @param  object  $obj
	 */
	public static function from($obj, array $items = []): self
	{
		$ro = new \ReflectionObject($obj);
		foreach ($ro->getProperties() as $prop) {
			$type = Nette\DI\Helpers::getPropertyType($prop);
			$item = &$items[$prop->getName()];
			if (!$item) {
				$item = new self($type ?? 'mixed');
				if (PHP_VERSION_ID >= 70400 && !$prop->isInitialized($obj)) {
					$item->required();
				} else {
					$item->default = $prop->getValue($obj);
					if (is_object($item->default)) {
						$item = self::from($item->default);
					} elseif ($item->default === null && !Nette\Utils\Validators::is(null, $item->type)) {
						$item->required();
					}
				}
			}
		}
		return self::structure($items)->castTo($ro->getName());
	}


	private function __construct(string $type)
	{
		static $defaults = ['array' => [], 'list' => [], 'structure' => []];
		$this->type = $type;
		$this->default = strpos($type, '[]') ? [] : $defaults[$type] ?? null;
	}


	public function default($value): self
	{
		if ($this->type === self::TYPE_STRUCTURE) {
			throw new Nette\InvalidStateException('Structure cannot have default value.');
		}
		$this->default = $value;
		return $this;
	}


	public function required(): self
	{
		$this->required = true;
		return $this;
	}


	public function nullable(): self
	{
		$this->type .= '|null';
		return $this;
	}


	/**
	 * @param  string|Schema  $type
	 */
	public function otherItems($type = 'mixed'): self
	{
		$this->otherItems = $type instanceof Schema ? $type : new self($type);
		return $this;
	}


	public function min(?float $min): self
	{
		$this->range[0] = $min;
		return $this;
	}


	public function max(?float $max): self
	{
		$this->range[1] = $max;
		return $this;
	}


	public function normalize(callable $handler): self
	{
		$this->normalize = $handler;
		return $this;
	}


	public function castTo(string $type): self
	{
		$this->castTo = $type;
		return $this;
	}


	public function assert(callable $handler): self
	{
		$this->asserts[] = $handler;
		return $this;
	}


	/********************* processing ****************d*g**/


	public function flatten(array $configs, array $path = [])
	{
		$flat = null;
		foreach ($configs as $config) {
			if (is_array($config) && isset($config[Helpers::PREVENT_MERGING])) {
				unset($config[Helpers::PREVENT_MERGING]);
				$flat = [];
			}

			if ($this->normalize) {
				$config = ($this->normalize)($config);
			}

			if (is_array($config)) {
				$flat = is_array($flat) ? $flat : [];
				$index = 0;
				foreach ($config as $key => $val) {
					if ($key === $index) {
						$flat[][] = $val;
						$index++;
					} else {
						$flat[$key][] = $val;
					}
				}
			} elseif ($config !== null || !is_array($flat)) {
				$flat = $config;
			}
		}

		if (is_array($flat)) {
			foreach ($flat as $key => $vals) {
				$itemSchema = $this->items[$key] ?? $this->otherItems;
				if ($itemSchema instanceof Schema) {
					$flat[$key] = $itemSchema->flatten($vals, array_merge($path, [$key]));
				} else {
					$flat[$key] = null;
					foreach ($vals as $val) {
						$flat[$key] = Helpers::merge($val, $flat[$key]);
					}
				}
			}
		}

		return $flat;
	}


	public function complete($value, array $path = [])
	{
		$label = $path ? "option '" . implode(' › ', $path) . "'" : 'option';

		if ($this->type === self::TYPE_ENUM) {
			$value = $this->completeEnum($value, $this->items, $path, $label);

		} else {
			if ($value === null && is_array($this->default)) {
				$value = []; // is unable to distinguish null from array in NEON
			}
			try {
				$expected = ($this->type === self::TYPE_STRUCTURE ? 'array' : $this->type)
					. ($this->range === [null, null] ? '' : ':' . implode('..', $this->range));
				Nette\Utils\Validators::assert($value, $expected, $label);
			} catch (Nette\Utils\AssertionException $e) {
				throw new InvalidConfigurationException($e->getMessage(), $path);
			}

			if ($this->items !== null || $this->otherItems) {
				$value = $this->completeStructure($value, $this->items ?? [], $this->otherItems, $path);
			}

			if ($this->type !== self::TYPE_STRUCTURE) {
				$value = Helpers::merge($value, $this->default);
			}
		}


		if ($this->castTo) {
			if (Nette\Utils\Reflection::isBuiltinType($this->castTo)) {
				settype($value, $this->castTo);
			} else {
				$value = Nette\Utils\Arrays::toObject($value, new $this->castTo);
			}
		}

		foreach ($this->asserts as $i => $assert) {
			if (!$assert($value)) {
				throw new InvalidConfigurationException('Failed assertion ' . (is_string($assert) ? "$assert()" : "#$i") . " for $label with value " . self::formatValue($value) . '.', $path);
			}
		}

		return $value;
	}


	private function completeEnum($value, array $items, array $path, string $label)
	{
		$hints = [];
		foreach ($items as $item) {
			if ($item instanceof self) {
				try {
					return $item->complete($value, $path);
				} catch (InvalidConfigurationException $e) {
					if (!isset($innerE) && count($e->getPath()) > count($path)) {
						$innerE = $e;
					}
				}
				$hints[] = $item->type . ($item->asserts ? '*' : '');
			} else {
				if ($item === $value) {
					return $value;
				}
				$hints[] = self::formatValue($item);
			}
		}

		$hints = implode('|', array_unique($hints));
		throw $innerE ?? new InvalidConfigurationException("The $label expects to be $hints, " . self::formatValue($value) . ' given.', $path);
	}


	private function completeStructure(array $value, array $items, $otherItems, array $path)
	{
		if ($extraKeys = array_keys(array_diff_key($value, $items))) {
			if ($otherItems) {
				$items += array_fill_keys($extraKeys, $otherItems);
			} else {
				$hint = Nette\Utils\ObjectHelpers::getSuggestion(array_map('strval', array_keys($items)), (string) $extraKeys[0]);
				$s = implode("', '", array_map(function ($key) use ($path) {
					return implode(' › ', array_merge($path, [$key]));
				}, $hint ? [$extraKeys[0]] : $extraKeys));
				throw new InvalidConfigurationException("Unexpected option '$s'" . ($hint ? ", did you mean '$hint'?" : '.'), $path);
			}
		}

		foreach ($items as $itemKey => $itemVal) {
			if ($itemVal instanceof Schema) {
				$items[$itemKey] = array_key_exists($itemKey, $value)
					? $itemVal->complete($value[$itemKey], array_merge($path, [$itemKey]))
					: $itemVal->getDefault(array_merge($path, [$itemKey]));
			} elseif (array_key_exists($itemKey, $value)) {
				$items[$itemKey] = Helpers::merge($value[$itemKey], $itemVal);
			} else {
				$items[$itemKey] = $itemVal;
			}
		}

		return $items;
	}


	public function getDefault(array $path = [])
	{
		if ($this->type === self::TYPE_STRUCTURE) {
			return $this->complete([], $path);
		} elseif ($this->required) {
			throw new InvalidConfigurationException("The mandatory option '" . implode(' › ', $path) . "' is missing.", $path);
		} else {
			return $this->default;
		}
	}


	private static function formatValue($value): string
	{
		if (is_string($value)) {
			return "'$value'";
		} elseif (is_bool($value)) {
			return $value ? 'true' : 'false';
		} elseif (is_scalar($value)) {
			return (string) $value;
		} else {
			return strtolower(gettype($value));
		}
	}
}
