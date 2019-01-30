<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI;

use Nette;
use Nette\Utils\Reflection;
use ReflectionClass;
use ReflectionMethod;


/**
 * Cache dependencies checker.
 */
class DependencyChecker
{
	use Nette\SmartObject;

	public const VERSION = 1;

	/** @var array of ReflectionClass|\ReflectionFunctionAbstract|string */
	private $dependencies = [];


	/**
	 * Adds dependencies to the list.
	 * @return static
	 */
	public function add(array $deps)
	{
		$this->dependencies = array_merge($this->dependencies, $deps);
		return $this;
	}


	/**
	 * Exports dependencies.
	 */
	public function export(): array
	{
		$files = $phpFiles = $classes = $functions = [];
		foreach ($this->dependencies as $dep) {
			if (is_string($dep)) {
				$files[] = $dep;

			} elseif ($dep instanceof ReflectionClass) {
				if (empty($classes[$name = $dep->getName()])) {
					$all = [$name] + class_parents($name) + class_implements($name);
					foreach ($all as &$item) {
						$all += class_uses($item);
						$phpFiles[] = (new ReflectionClass($item))->getFileName();
						$classes[$item] = true;
					}
				}

			} elseif ($dep instanceof \ReflectionFunctionAbstract) {
				$phpFiles[] = $dep->getFileName();
				$functions[] = Reflection::toString($dep);

			} else {
				throw new Nette\InvalidStateException('Unexpected dependency ' . gettype($dep));
			}
		}

		$classes = array_keys($classes);
		$functions = array_unique($functions, SORT_REGULAR);
		$hash = self::calculateHash($classes, $functions);
		$files = @array_map('filemtime', array_combine($files, $files)); // @ - file may not exist
		$phpFiles = @array_map('filemtime', array_combine($phpFiles, $phpFiles)); // @ - file may not exist
		return [self::VERSION, $files, $phpFiles, $classes, $functions, $hash];
	}


	/**
	 * Are dependencies expired?
	 */
	public static function isExpired(int $version, array $files, array &$phpFiles, array $classes, array $functions, string $hash): bool
	{
		try {
			$current = @array_map('filemtime', array_combine($tmp = array_keys($files), $tmp)); // @ - files may not exist
			$origPhpFiles = $phpFiles;
			$phpFiles = @array_map('filemtime', array_combine($tmp = array_keys($phpFiles), $tmp)); // @ - files may not exist
			return $version !== self::VERSION
				|| $files !== $current
				|| ($phpFiles !== $origPhpFiles && $hash !== self::calculateHash($classes, $functions));
		} catch (\ReflectionException $e) {
			return true;
		}
	}


	private static function calculateHash(array $classes, array $functions): string
	{
		$hash = [];
		foreach ($classes as $name) {
			$class = new ReflectionClass($name);
			$hash[] = [
				$name,
				Reflection::getUseStatements($class),
				$class->isAbstract(),
				get_parent_class($name),
				class_implements($name),
				class_uses($name),
			];

			foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
				if ($prop->getDeclaringClass() == $class) { // intentionally ==
					$hash[] = [$name, $prop->getName(), $prop->getDocComment()];
				}
			}
			foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
				if ($method->getDeclaringClass() == $class) { // intentionally ==
					$hash[] = [
						$name,
						$method->getName(),
						$method->getDocComment(),
						self::hashParameters($method),
						$method->hasReturnType()
							? [(string) $method->getReturnType(), $method->getReturnType()->allowsNull()]
							: null,
					];
				}
			}
		}

		$flip = array_flip($classes);
		foreach ($functions as $name) {
			$method = strpos($name, '::') ? new ReflectionMethod($name) : new \ReflectionFunction($name);
			$class = $method instanceof ReflectionMethod ? $method->getDeclaringClass() : null;
			if ($class && isset($flip[$class->getName()])) {
				continue;
			}
			$hash[] = [
				$name,
				$class ? Reflection::getUseStatements($method->getDeclaringClass()) : null,
				$method->getDocComment(),
				self::hashParameters($method),
				$method->hasReturnType()
					? [(string) $method->getReturnType(), $method->getReturnType()->allowsNull()]
					: null,
			];
		}

		return md5(serialize($hash));
	}


	private static function hashParameters(\ReflectionFunctionAbstract $method): array
	{
		$res = [];
		foreach ($method->getParameters() as $param) {
			$res[] = [
				$param->getName(),
				Reflection::getParameterType($param),
				$param->allowsNull(),
				$param->isVariadic(),
				$param->isDefaultValueAvailable()
					? [Reflection::getParameterDefaultValue($param)]
					: null,
			];
		}
		return $res;
	}
}
