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

	/** @var ContainerBuilder */
	private $builder;

	/** @var array */
	private $classList = [];

	/** @var string[] of classes excluded from auto-wiring */
	private $excludedClasses = [];


	public function __construct(ContainerBuilder $builder)
	{
		$this->builder = $builder;
	}


	/**
	 * Resolves service name by type.
	 * @param  bool  $throw exception if service not found?
	 * @throws MissingServiceException when not found
	 * @throws ServiceCreationException when multiple found
	 */
	public function getByType(string $type, bool $throw = false): ?string
	{
		$type = Helpers::normalizeClass($type);
		$types = $this->classList;
		if (empty($types[$type][true])) {
			if ($throw) {
				throw new MissingServiceException("Service of type '$type' not found.");
			}
			return null;

		} elseif (count($types[$type][true]) === 1) {
			return $types[$type][true][0];

		} else {
			$list = $types[$type][true];
			natsort($list);
			$hint = count($list) === 2 && ($tmp = strpos($list[0], '.') xor strpos($list[1], '.'))
				? '. If you want to overwrite service ' . $list[$tmp ? 0 : 1] . ', give it proper name.'
				: '';
			throw new ServiceCreationException("Multiple services of type $type found: " . implode(', ', $list) . $hint);
		}
	}


	/**
	 * Gets the service names and definitions of the specified type.
	 * @return Definitions\ServiceDefinition[]  service name is key
	 */
	public function findByType(string $type): array
	{
		$type = Helpers::normalizeClass($type);
		$found = [];
		$types = $this->classList;
		$definitions = $this->builder->getDefinitions();
		if (!empty($types[$type])) {
			foreach (array_merge(...array_values($types[$type])) as $name) {
				$found[$name] = $definitions[$name];
			}
		}
		return $found;
	}


	/**
	 * @param  string[]  $types
	 */
	public function addExcludedClasses(array $types): void
	{
		foreach ($types as $type) {
			if (class_exists($type) || interface_exists($type)) {
				$type = Helpers::normalizeClass($type);
				$this->excludedClasses += class_parents($type) + class_implements($type) + [$type => $type];
			}
		}
	}


	public function getClassList(): array
	{
		return $this->classList;
	}


	public function rebuild(): void
	{
		$this->classList = $preferred = [];

		foreach ($this->builder->getDefinitions() as $name => $def) {
			if (!($type = $def->getImplement() ?: $def->getType())) {
				continue;
			}

			$defAutowired = $def->getAutowired();
			if (is_array($defAutowired)) {
				foreach ($defAutowired as $k => $autowiredType) {
					if ($autowiredType === ContainerBuilder::THIS_SERVICE) {
						$defAutowired[$k] = $type;
					} elseif (!is_a($type, $autowiredType, true)) {
						throw new ServiceCreationException("Incompatible class $autowiredType in autowiring definition of service '$name'.");
					}
				}
			}

			foreach (class_parents($type) + class_implements($type) + [$type] as $parent) {
				$autowired = $defAutowired && empty($this->excludedClasses[$parent]);
				if ($autowired && is_array($defAutowired)) {
					$autowired = false;
					foreach ($defAutowired as $autowiredType) {
						if (is_a($parent, $autowiredType, true)) {
							if (empty($preferred[$parent]) && isset($this->classList[$parent][true])) {
								$this->classList[$parent][false] = array_merge(...$this->classList[$parent]);
								$this->classList[$parent][true] = [];
							}
							$preferred[$parent] = $autowired = true;
							break;
						}
					}
				} elseif (isset($preferred[$parent])) {
					$autowired = false;
				}
				$this->classList[$parent][$autowired][] = (string) $name;
			}
		}
	}


	/**
	 * Generates list of arguments using autowiring.
	 * @param  Resolver|Container  $container
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
					$res[$num] = $container->getByType($type);
				} catch (MissingServiceException $e) {
					$res[$num] = null;
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
