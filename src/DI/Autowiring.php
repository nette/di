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

	/** @var array[]  type => services, used by getByType() */
	private $highPriority = [];

	/** @var array[]  type => services, used by findByType() */
	private $lowPriority = [];

	/** @var string[] of classes excluded from autowiring */
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
		$types = $this->highPriority;
		if (empty($types[$type])) {
			if ($throw) {
				throw new MissingServiceException("Service of type '$type' not found.");
			}
			return null;

		} elseif (count($types[$type]) === 1) {
			return $types[$type][0];

		} else {
			$list = $types[$type];
			natsort($list);
			$hint = count($list) === 2 && ($tmp = strpos($list[0], '.') xor strpos($list[1], '.'))
				? '. If you want to overwrite service ' . $list[$tmp ? 0 : 1] . ', give it proper name.'
				: '';
			throw new ServiceCreationException("Multiple services of type $type found: " . implode(', ', $list) . $hint);
		}
	}


	/**
	 * Gets the service names and definitions of the specified type.
	 * @return Definitions\Definition[]  service name is key
	 */
	public function findByType(string $type): array
	{
		$type = Helpers::normalizeClass($type);
		$definitions = $this->builder->getDefinitions();
		$names = array_merge($this->highPriority[$type] ?? [], $this->lowPriority[$type] ?? []);
		$res = [];
		foreach ($names as $name) {
			$res[$name] = $definitions[$name];
		}
		return $res;
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
		return [$this->lowPriority, $this->highPriority];
	}


	public function rebuild(): void
	{
		$this->lowPriority = $this->highPriority = $preferred = [];

		foreach ($this->builder->getDefinitions() as $name => $def) {
			if (!($type = $def->getType())) {
				continue;
			}

			$autowired = $def->getAutowired();
			if (is_array($autowired)) {
				foreach ($autowired as $k => $autowiredType) {
					if ($autowiredType === ContainerBuilder::THIS_SERVICE) {
						$autowired[$k] = $type;
					} elseif (!is_a($type, $autowiredType, true)) {
						throw new ServiceCreationException("Incompatible class $autowiredType in autowiring definition of service '$name'.");
					}
				}
			}

			foreach (class_parents($type) + class_implements($type) + [$type] as $parent) {
				if (!$autowired || isset($this->excludedClasses[$parent])) {
					continue;
				} elseif (is_array($autowired)) {
					$priority = false;
					foreach ($autowired as $autowiredType) {
						if (is_a($parent, $autowiredType, true)) {
							if (empty($preferred[$parent]) && isset($this->highPriority[$parent])) {
								$this->lowPriority[$parent] = array_merge($this->lowPriority[$parent], $this->highPriority[$parent]);
								$this->highPriority[$parent] = [];
							}
							$preferred[$parent] = $priority = true;
							break;
						}
					}
				} else {
					$priority = empty($preferred[$parent]);
				}
				$list = $priority ? 'highPriority' : 'lowPriority';
				$this->$list[$parent][] = (string) $name;
			}
		}
	}


	/**
	 * Generates list of arguments using autowiring.
	 * @param  Resolver|Container  $container
	 * @throws ServiceCreationException
	 */
	public static function completeArguments(\ReflectionFunctionAbstract $method, array $arguments, $container, Definitions\Definition $current = null): array
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

			} elseif (
				$method instanceof \ReflectionMethod
				&& $parameter->isArray()
				&& preg_match('#@param[ \t]+([\w\\\\]+)\[\][ \t]+\$' . $paramName . '#', (string) $method->getDocComment(), $m)
				&& ($type = Reflection::expandClassName($m[1], $method->getDeclaringClass()))
				&& (class_exists($type) || interface_exists($type))
			) {
				$src = $container instanceof Resolver ? $container->getContainerBuilder() : $container;
				$res[$num] = [];
				foreach ($src->findAutowired($type) as $item) {
					if ($item !== $current) {
						$res[$num][] = $item;
					}
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
