<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI;

use Nette;
use Nette\Utils\Reflection;


/**
 * Autowiring.
 */
class Autowiring
{
	use Nette\SmartObject;

	/**
	 * Generates list of arguments using autowiring.
	 * @throws ServiceCreationException
	 */
	public static function completeArguments(\ReflectionFunctionAbstract $method, array $arguments, $container): array
	{
		$optCount = 0;
		$num = -1;
		$res = [];
		$methodName = Reflection::toString($method) . '()';

		foreach ($method->getParameters() as $num => $parameter) {
			$paramName = $parameter->getName();
			if (!$parameter->isVariadic() && array_key_exists($paramName, $arguments)) {
				$res[$num] = $arguments[$paramName];
				unset($arguments[$paramName], $arguments[$num]);
				$optCount = 0;

			} elseif (array_key_exists($num, $arguments)) {
				$res[$num] = $arguments[$num];
				unset($arguments[$num]);
				$optCount = 0;

			} elseif (($type = Reflection::getParameterType($parameter)) && !Reflection::isBuiltinType($type)) {
				try {
					$res[$num] = $container->getByType($type, false);
				} catch (ServiceCreationException $e) {
					throw new ServiceCreationException("{$e->getMessage()} (needed by $$paramName in $methodName)", 0, $e);
				}
				if ($res[$num] === null) {
					if ($parameter->allowsNull()) {
						$optCount++;
					} elseif (class_exists($type) || interface_exists($type)) {
						throw new ServiceCreationException("Service of type $type needed by $$paramName in $methodName not found. Did you register it in configuration file?");
					} else {
						throw new ServiceCreationException("Class $type needed by $$paramName in $methodName not found. Check type hint and 'use' statements.");
					}
				} else {
					if ($container instanceof ContainerBuilder) {
						$res[$num] = '@' . $res[$num];
					}
					$optCount = 0;
				}

			} elseif (($type && $parameter->allowsNull()) || $parameter->isOptional() || $parameter->isDefaultValueAvailable()) {
				// !optional + defaultAvailable = func($a = null, $b) since 5.4.7
				// optional + !defaultAvailable = i.e. Exception::__construct, mysqli::mysqli, ...
				$res[$num] = $parameter->isDefaultValueAvailable() ? Reflection::getParameterDefaultValue($parameter) : null;
				$optCount++;

			} else {
				throw new ServiceCreationException("Parameter $$paramName in $methodName has no class type hint or default value, so its value must be specified.");
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
}
