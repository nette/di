<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI;

use Nette;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\Statement;
use Nette\Utils\Reflection;
use Nette\Utils\Type;


/**
 * The DI helpers.
 * @internal
 */
final class Helpers
{
	use Nette\StaticClass;

	/**
	 * Expands %placeholders%.
	 * @param  mixed  $var
	 * @param  bool|array  $recursive
	 * @return mixed
	 * @throws Nette\InvalidArgumentException
	 */
	public static function expand($var, array $params, $recursive = false)
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
			return $recursive
				? self::expand($params, $params, $recursive)
				: $params;

		} elseif (is_string($var)) {
			$recursive = is_array($recursive) ? $recursive : ($recursive ? [] : null);
			return self::expandString($var, $params, $recursive);

		} else {
			return $var;
		}
	}


	/**
	 * Expands %placeholders% in string
	 * @throws Nette\InvalidArgumentException
	 */
	private static function expandString(string $string, array $params, ?array $recursive, bool $onlyString = false)
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
			? new Statement('::implode', ['', $res])
			: implode('', $res);
	}


	private static function expandParameter(string $parameter, array $params, ?array $recursive, bool $onlyString)
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
						: self::expandString($val, $params, $recursive + [$pathStr => 1], true);
				}
			} elseif ($val instanceof DynamicParameter) {
				$val = new DynamicParameter($val . '[' . var_export($key, true) . ']');
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
	 * @param  mixed  $value
	 * @return mixed
	 */
	public static function escape($value)
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
	 * Process constants recursively.
	 */
	public static function filterArguments(array $args): array
	{
		foreach ($args as $k => $v) {
			if (
				is_string($v)
				&& preg_match('#^([\w\\\\]+)::\w+$#D', $v, $m)
				&& enum_exists($m[1])
			) {
				$args[$k] = new Nette\PhpGenerator\PhpLiteral($v);
			} elseif (is_string($v) && preg_match('#^[\w\\\\]*::[A-Z][a-zA-Z0-9_]*$#D', $v)) {
				$args[$k] = new Nette\PhpGenerator\PhpLiteral(ltrim($v, ':'));
			} elseif (is_string($v) && preg_match('#^@[\w\\\\]+$#D', $v)) {
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
	 * @param  mixed  $config
	 * @return mixed
	 */
	public static function prefixServiceName($config, string $namespace)
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
	 * @param  \ReflectionFunctionAbstract|\ReflectionProperty|\ReflectionClass  $ref
	 */
	public static function parseAnnotation(\Reflector $ref, string $name): ?string
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


	public static function getReturnTypeAnnotation(\ReflectionFunctionAbstract $func): ?Type
	{
		$type = preg_replace('#[|\s].*#', '', (string) self::parseAnnotation($func, 'return'));
		if (!$type || $type === 'object' || $type === 'mixed') {
			return null;
		} elseif ($func instanceof \ReflectionMethod) {
			$type = $type === '$this' ? 'static' : $type;
			$type = Reflection::expandClassName($type, $func->getDeclaringClass());
		}

		return Type::fromString($type);
	}


	public static function ensureClassType(?Type $type, string $hint, bool $allowNullable = false): string
	{
		if (!$type) {
			throw new ServiceCreationException(sprintf('%s is not declared.', ucfirst($hint)));
		} elseif (!$type->isClass() || (!$allowNullable && $type->allows('null'))) {
			throw new ServiceCreationException(sprintf("%s is expected to not be %sbuilt-in/complex, '%s' given.", ucfirst($hint), $allowNullable ? '' : 'nullable/', $type));
		}

		$class = $type->getSingleName();
		if (!class_exists($class) && !interface_exists($class)) {
			throw new ServiceCreationException(sprintf("Class '%s' not found.\nCheck the %s.", $class, $hint));
		}

		return $class;
	}


	public static function normalizeClass(string $type): string
	{
		return class_exists($type) || interface_exists($type)
			? (new \ReflectionClass($type))->name
			: $type;
	}


	/**
	 * Non data-loss type conversion.
	 * @param  mixed  $value
	 * @return mixed
	 * @throws Nette\InvalidStateException
	 */
	public static function convertType($value, string $type)
	{
		if (is_scalar($value)) {
			$norm = ($value === false ? '0' : (string) $value);
			if ($type === 'float') {
				$norm = preg_replace('#\.0*$#D', '', $norm);
			}

			$orig = $norm;
			settype($norm, $type);
			if ($orig === ($norm === false ? '0' : (string) $norm)) {
				return $norm;
			}
		}

		throw new Nette\InvalidStateException(sprintf(
			'Cannot convert %s to %s.',
			is_scalar($value) ? "'$value'" : gettype($value),
			$type,
		));
	}
}
