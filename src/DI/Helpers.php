<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI;

use Nette;
use Nette\DI\Compiler\DynamicParameter;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\Statement;
use Nette\Utils\Reflection;
use Nette\Utils\Type;
use function array_key_exists, count, in_array, is_array, is_scalar, is_string, sprintf, strlen;


/**
 * The DI helpers.
 * @internal
 */
final class Helpers
{
	use Nette\StaticClass;

	/**
	 * Expands %placeholders%.
	 * @param  array<string, mixed>  $params
	 * @param  bool|array<string, int>  $recursive
	 * @throws Nette\InvalidArgumentException
	 */
	public static function expand(mixed $var, array $params, bool|array $recursive = false): mixed
	{
		if (is_array($var)) {
			$res = [];
			foreach ($var as $key => $val) {
				$res[self::expand($key, $params, $recursive)] = self::expand($val, $params, $recursive);
			}
			return $res;

		} elseif ($var instanceof Statement) {
			return new Statement(
				self::expand($var->getEntity(), $params, $recursive),
				self::expand($var->arguments, $params, $recursive),
			);

		} elseif ($var === '%parameters%' && !array_key_exists('parameters', $params)) {
			throw new Nette\DeprecatedException('%parameters% is deprecated, use @container::getParameters()');

		} elseif (is_string($var)) {
			$recursive = is_array($recursive) ? $recursive : ($recursive ? [] : null);
			return self::expandString($var, $params, $recursive);

		} else {
			return $var;
		}
	}


	/**
	 * Expands %placeholders% in string
	 * @param  array<string, mixed>  $params
	 * @param  ?array<string, int>  $recursive
	 * @throws Nette\InvalidArgumentException
	 */
	private static function expandString(
		string $string,
		array $params,
		?array $recursive,
		bool $onlyString = false,
	): mixed
	{
		$parts = preg_split('#%([\w.-]*)%#i', $string, -1, PREG_SPLIT_DELIM_CAPTURE);
		$res = [];
		$dynamic = false;
		foreach ($parts as $n => $part) {
			if ($n % 2 === 0) {
				$res[] = $part;
			} elseif ($part === '') {
				$res[] = '%';
			} else {
				$res[] = $val = self::expandParameter($part, $params, $recursive, $onlyString);
				if (strlen($part) + 2 === strlen($string)) {
					return $val;
				} elseif ($val instanceof DynamicParameter || $val instanceof Statement) {
					$dynamic = true;
				} elseif (!is_scalar($val)) {
					throw new Nette\InvalidArgumentException(sprintf("Unable to concatenate non-scalar parameter '%s' into '%s'.", $part, $string));
				}
			}
		}

		return $dynamic
			? new Statement('::implode', [$res])
			: implode('', $res);
	}


	/**
	 * @param  array<string, mixed>  $params
	 * @param  ?array<string, int>  $recursive
	 */
	private static function expandParameter(
		string $parameter,
		array $params,
		?array $recursive,
		bool $onlyString,
	): mixed
	{
		$val = $params;
		$path = [];
		$keys = explode('.', $parameter);
		while (($key = $path[] = array_shift($keys)) !== null) {
			if (is_array($val) && array_key_exists($key, $val)) {
				$val = $val[$key];
				$fullExpand = !$onlyString && !$keys; // last
				if (is_array($recursive) && ($fullExpand || is_string($val))) {
					$pathStr = implode('.', $path);
					if (isset($recursive[$pathStr])) {
						throw new Nette\InvalidArgumentException('Circular reference detected for parameters: %' . implode('%, %', array_keys($recursive)) . '%');
					}
					$val = $fullExpand
						? self::expand($val, $params, $recursive + [$pathStr => 1])
						: self::expandString($val, $params, $recursive + [$pathStr => 1], onlyString: true);
				}
			} elseif ($val instanceof DynamicParameter) {
				$val = new DynamicParameter($val . '[' . var_export($key, return: true) . ']');
			} elseif ($val instanceof Statement) {
				$val = new Statement('(?)[?]', [$val, $key]);
			} else {
				throw new Nette\InvalidArgumentException(sprintf("Missing parameter '%s'.", $parameter));
			}
		}
		return $val;
	}


	/**
	 * Escapes '%' and '@'
	 */
	public static function escape(mixed $value): mixed
	{
		if (is_array($value)) {
			$res = [];
			foreach ($value as $key => $val) {
				$key = is_string($key) ? str_replace('%', '%%', $key) : $key;
				$res[$key] = self::escape($val);
			}

			return $res;
		} elseif (is_string($value)) {
			return preg_replace('#^@|%#', '$0$0', $value);
		}

		return $value;
	}


	/**
	 * Converts @service strings to Reference objects recursively.
	 * @param  array<mixed>  $args
	 * @return array<mixed>
	 */
	public static function filterArguments(array $args): array
	{
		foreach ($args as $k => $v) {
			if (is_string($v) && preg_match('#^@[\w\\\]+$#D', $v)) {
				$args[$k] = new Reference(substr($v, 1));
			} elseif (is_array($v)) {
				$args[$k] = self::filterArguments($v);
			} elseif ($v instanceof Statement) {
				[$tmp] = self::filterArguments([$v->getEntity()]);
				$args[$k] = new Statement($tmp, self::filterArguments($v->arguments));
			}
		}

		return $args;
	}


	/**
	 * Replaces @extension with real extension name in service definition.
	 */
	public static function prefixServiceName(mixed $config, string $namespace): mixed
	{
		if (is_string($config)) {
			if (strncmp($config, '@extension.', 10) === 0) {
				$config = '@' . $namespace . '.' . substr($config, 11);
			}
		} elseif ($config instanceof Reference) {
			if (strncmp($config->getValue(), 'extension.', 9) === 0) {
				$config = new Reference($namespace . '.' . substr($config->getValue(), 10));
			}
		} elseif ($config instanceof Statement) {
			return new Statement(
				self::prefixServiceName($config->getEntity(), $namespace),
				self::prefixServiceName($config->arguments, $namespace),
			);
		} elseif (is_array($config)) {
			foreach ($config as &$val) {
				$val = self::prefixServiceName($val, $namespace);
			}
		}

		return $config;
	}


	/**
	 * Returns an annotation value.
	 * @param  \ReflectionClass<object>|\ReflectionFunctionAbstract|\ReflectionProperty  $ref
	 */
	public static function parseAnnotation(
		\ReflectionFunctionAbstract|\ReflectionProperty|\ReflectionClass $ref,
		string $name,
	): ?string
	{
		if (!Reflection::areCommentsAvailable()) {
			throw new Nette\InvalidStateException('You have to enable phpDoc comments in opcode cache.');
		}

		$re = '#[\s*]@' . preg_quote($name, '#') . '(?=\s|$)(?:[ \t]+([^@\s]\S*))?#';
		if ($ref->getDocComment() && preg_match($re, trim($ref->getDocComment(), '/*'), $m)) {
			return $m[1] ?? '';
		}

		return null;
	}


	/**
	 * Validates that the type is a non-nullable class type and returns the class name.
	 * @return class-string
	 * @throws ServiceCreationException
	 */
	public static function ensureClassType(?Type $type, string $hint, bool $allowNullable = false): string
	{
		if (!$type) {
			throw new ServiceCreationException(sprintf('%s is not declared.', ucfirst($hint)));
		} elseif (!$type->isClass() || (!$allowNullable && $type->allows('null'))) {
			throw new ServiceCreationException(sprintf("%s is expected to not be %sbuilt-in/complex, '%s' given.", ucfirst($hint), $allowNullable ? '' : 'nullable/', $type));
		}

		$class = $type->getSingleName();
		if ($class === null) {
			throw new ServiceCreationException(sprintf('%s is not declared.', ucfirst($hint)));
		} elseif (!class_exists($class) && !interface_exists($class)) {
			throw new ServiceCreationException(sprintf("Class '%s' not found.\nCheck the %s.", $class, $hint));
		}

		return $class;
	}


	/**
	 * Normalizes class name to its canonical form using reflection.
	 * @return class-string
	 */
	public static function normalizeClass(string $type): string
	{
		return class_exists($type) || interface_exists($type)
			? (new \ReflectionClass($type))->name
			: $type;
	}


	/**
	 * Non data-loss type conversion.
	 * @throws Nette\InvalidStateException
	 */
	public static function convertType(mixed $value, string $type): mixed
	{
		if (is_scalar($value)) {
			$norm = ($value === false ? '0' : (string) $value);
			if ($type === 'float') {
				$norm = preg_replace('#\.0*$#D', '', $norm);
			}

			$converted = match ($type) {
				'bool' => (bool) $norm,
				'int' => (int) $norm,
				'float' => (float) $norm,
				'string' => $norm,
				default => null,
			};
			if ($converted !== null && $norm === ($converted === false ? '0' : (string) $converted)) {
				return $converted;
			}
		}

		throw new Nette\InvalidStateException(sprintf(
			'Cannot convert %s to %s.',
			is_scalar($value) ? "'$value'" : get_debug_type($value),
			$type,
		));
	}


	/**
	 * Orders items by their before/after constraints using topological sort (Kahn's algorithm).
	 * A target matches items whose 'owner' passes is_a() (so subclasses match); '*' matches all
	 * others except those with the same wildcard. Ties keep the original order.
	 * @template TKey of array-key
	 * @param  array<TKey, array{owner: ?object, before: string|string[]|null, after: string|string[]|null}>  $items
	 * @return list<TKey>
	 * @throws Nette\InvalidStateException  on circular dependency
	 */
	public static function sortByConstraints(array $items): array
	{
		$keys = array_keys($items);
		$pos = array_flip($keys);
		$graph = array_fill_keys($keys, []);
		$inDegree = array_fill_keys($keys, 0);

		$owners = null;
		$match = function (string $target, string $dir) use ($items, &$owners): array {
			if ($target === '*') {
				return array_keys(array_filter($items, fn($item) => !in_array('*', (array) ($item[$dir] ?? []), true)));
			}

			$res = array_keys(array_filter($items, fn($item) => $item['owner'] && is_a($item['owner'], $target)));
			if (!$res && !class_exists($target) && !interface_exists($target)) {
				// an absent optional extension is legitimate and stays silent; but when the name resembles a present one, it is most likely a typo - say so
				$owners ??= array_unique(array_filter(array_map(fn($item) => $item['owner'] === null ? null : $item['owner']::class, $items)));
				if ($hint = Nette\Utils\Helpers::getSuggestion($owners, $target)) {
					trigger_error("Hook ordering constraint '$dir: $target' refers to a class that does not exist and was ignored, did you mean '$hint'?", E_USER_WARNING);
				}
			}

			return $res;
		};

		foreach ($items as $key => $item) {
			foreach ((array) ($item['before'] ?? []) as $target) {
				foreach ($match($target, 'before') as $other) {
					if ($key !== $other) {
						$graph[$key][] = $other;
						$inDegree[$other]++;
					}
				}
			}

			foreach ((array) ($item['after'] ?? []) as $target) {
				foreach ($match($target, 'after') as $other) {
					if ($key !== $other) {
						$graph[$other][] = $key;
						$inDegree[$key]++;
					}
				}
			}
		}

		$queue = array_values(array_filter($keys, fn($k) => $inDegree[$k] === 0));
		$result = [];
		while ($queue) {
			usort($queue, fn($a, $b) => $pos[$a] <=> $pos[$b]);
			$key = array_shift($queue);
			$result[] = $key;
			foreach ($graph[$key] as $next) {
				if (--$inDegree[$next] === 0) {
					$queue[] = $next;
				}
			}
		}

		if (count($result) !== count($keys)) {
			$stuck = array_filter(array_map(
				fn($k) => in_array($k, $result, true) || !$items[$k]['owner'] ? null : $items[$k]['owner']::class,
				$keys,
			));
			throw new Nette\InvalidStateException('Circular dependency detected in extension hooks'
				. ($stuck ? ': ' . implode(', ', array_unique($stuck)) : '.'));
		}

		return $result;
	}
}
