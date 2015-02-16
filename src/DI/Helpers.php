<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\DI;

use Nette;


/**
 * The DI helpers.
 *
 * @author     David Grudl
 * @internal
 */
class Helpers
{

	/**
	 * Expands %placeholders%.
	 * @param  mixed
	 * @param  array
	 * @param  bool
	 * @return mixed
	 * @throws Nette\InvalidArgumentException
	 */
	public static function expand($var, array $params, $recursive = FALSE)
	{
		if (is_array($var)) {
			$res = array();
			foreach ($var as $key => $val) {
				$res[$key] = self::expand($val, $params, $recursive);
			}
			return $res;

		} elseif ($var instanceof Statement) {
			return new Statement(self::expand($var->getEntity(), $params, $recursive), self::expand($var->arguments, $params, $recursive));

		} elseif (!is_string($var)) {
			return $var;
		}

		$parts = preg_split('#%([\w.-]*)%#i', $var, -1, PREG_SPLIT_DELIM_CAPTURE);
		$res = '';
		foreach ($parts as $n => $part) {
			if ($n % 2 === 0) {
				$res .= $part;

			} elseif ($part === '') {
				$res .= '%';

			} elseif (isset($recursive[$part])) {
				throw new Nette\InvalidArgumentException(sprintf('Circular reference detected for variables: %s.', implode(', ', array_keys($recursive))));

			} else {
				try {
					$val = Nette\Utils\Arrays::get($params, explode('.', $part));
				} catch (Nette\InvalidArgumentException $e) {
					throw new Nette\InvalidArgumentException("Missing parameter '$part'.", 0, $e);
				}
				if ($recursive) {
					$val = self::expand($val, $params, (is_array($recursive) ? $recursive : array()) + array($part => 1));
				}
				if (strlen($part) + 2 === strlen($var)) {
					return $val;
				}
				if (!is_scalar($val)) {
					throw new Nette\InvalidArgumentException("Unable to concatenate non-scalar parameter '$part' into '$var'.");
				}
				$res .= $val;
			}
		}
		return $res;
	}


	/**
	 * Generates list of arguments using autowiring.
	 * @return array
	 */
	public static function autowireArguments(\ReflectionFunctionAbstract $method, array $arguments, $container)
	{
		$optCount = 0;
		$num = -1;
		$res = array();
		$methodName = ($method instanceof \ReflectionMethod ? $method->getDeclaringClass()->getName() . '::' : '')
			. $method->getName() . '()';

		foreach ($method->getParameters() as $num => $parameter) {
			if (array_key_exists($num, $arguments)) {
				$res[$num] = $arguments[$num];
				unset($arguments[$num]);
				$optCount = 0;

			} elseif (array_key_exists($parameter->getName(), $arguments)) {
				$res[$num] = $arguments[$parameter->getName()];
				unset($arguments[$parameter->getName()]);
				$optCount = 0;

			} elseif ($class = PhpReflection::getPropertyType($parameter)) { // has object type hint
				$res[$num] = $container->getByType($class, FALSE);
				if ($res[$num] === NULL) {
					if ($parameter->allowsNull()) {
						$optCount++;
					} elseif (class_exists($class) || interface_exists($class)) {
						throw new ServiceCreationException("Service of type {$class} needed by $methodName not found. Did you register it in configuration file?");
					} else {
						throw new ServiceCreationException("Class {$class} needed by $methodName not found. Check type hint and 'use' statements.");
					}
				} else {
					if ($container instanceof ContainerBuilder) {
						$res[$num] = '@' . $res[$num];
					}
					$optCount = 0;
				}

			} elseif ($parameter->isOptional() || $parameter->isDefaultValueAvailable()) {
				// !optional + defaultAvailable = func($a = NULL, $b) since 5.3.17 & 5.4.7
				// optional + !defaultAvailable = i.e. Exception::__construct, mysqli::mysqli, ...
				$res[$num] = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : NULL;
				$optCount++;

			} else {
				throw new ServiceCreationException("Parameter \${$parameter->getName()} in $methodName has no type hint, so its value must be specified.");
			}
		}

		// extra parameters
		while (array_key_exists(++$num, $arguments)) {
			$res[$num] = $arguments[$num];
			unset($arguments[$num]);
			$optCount = 0;
		}
		if ($arguments) {
			throw new ServiceCreationException("Unable to pass specified arguments to $methodName.");
		}

		return $optCount ? array_slice($res, 0, -$optCount) : $res;
	}


	/**
	 * Generates list of properties with annotation @inject.
	 * @return array
	 */
	public static function getInjectProperties(\ReflectionClass $class, $container = NULL)
	{
		$res = array();
		foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
			$type = PhpReflection::parseAnnotation($property, 'var');
			if (PhpReflection::parseAnnotation($property, 'inject') === NULL) {
				continue;

			} elseif (!$type) {
				throw new Nette\InvalidStateException("Property $property has no @var annotation.");
			}

			$type = PhpReflection::expandClassName($type, PhpReflection::getDeclaringClass($property));
			if (!class_exists($type) && !interface_exists($type)) {
				throw new Nette\InvalidStateException("Class or interface '$type' used in @var annotation at $property not found. Check annotation and 'use' statements.");
			} elseif ($container && !$container->getByType($type, FALSE)) {
				throw new ServiceCreationException("Service of type {$type} used in @var annotation at $property not found. Did you register it in configuration file?");
			}
			$res[$property->getName()] = $type;
		}
		return $res;
	}

}
