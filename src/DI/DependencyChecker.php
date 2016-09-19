<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI;

use Nette;
use ReflectionClass;
use ReflectionMethod;


/**
 * Cache dependencies checker.
 */
class DependencyChecker
{
	const VERSION = 1;

	use Nette\SmartObject;

	/** @var array of ReflectionClass|\ReflectionFunctionAbstract|string */
	private $dependencies = [];


	/**
	 * Adds dependencies to the list.
	 * @return self
	 */
	public function add(array $deps)
	{
		$this->dependencies = array_merge($this->dependencies, $deps);
		return $this;
	}


	/**
	 * Exports dependencies.
	 * @return array
	 */
	public function export()
	{
		$files = $phpFiles = $classes = $functions = [];
		foreach ($this->dependencies as $dep) {
			if (is_string($dep)) {
				$files[] = $dep;

			} elseif ($dep instanceof ReflectionClass) {
				if (empty($classes[$dep->getName()])) {
					foreach (PhpReflection::getClassTree($dep) as $item) {
						$phpFiles[] = (new ReflectionClass($item))->getFileName();
						$classes[$item] = TRUE;
					}
				}

			} elseif ($dep instanceof \ReflectionFunctionAbstract) {
				$phpFiles[] = $dep->getFileName();
				$functions[] = $dep instanceof ReflectionMethod ? $dep->getDeclaringClass()->getName() . '::' . $dep->getName() : $dep->getName();

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
	 * @return bool
	 */
	public static function isExpired($version, $files, $phpFiles, $classes, $functions, $hash)
	{
		$current = @array_map('filemtime', array_combine($tmp = array_keys($files), $tmp)); // @ - files may not exist
		$currentClass = @array_map('filemtime', array_combine($tmp = array_keys($phpFiles), $tmp)); // @ - files may not exist
		return $version !== self::VERSION
			|| $files !== $current
			|| ($phpFiles !== $currentClass && $hash !== self::calculateHash($classes, $functions));
	}


	private static function calculateHash($classes, $functions)
	{
		$hash = [];
		foreach ($classes as $name) {
			try {
				$class = new ReflectionClass($name);
			} catch (\ReflectionException $e) {
				return;
			}
			$hash[] = [$name, PhpReflection::getUseStatements($class), $class->isAbstract()];
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
						PHP_VERSION_ID >= 70000 ? $method->getReturnType() : NULL
					];
				}
			}
		}

		$flip = array_flip($classes);
		foreach ($functions as $name) {
			try {
				$method = strpos($name, '::') ? new ReflectionMethod($name) : new \ReflectionFunction($name);
			} catch (\ReflectionException $e) {
				return;
			}
			$class = $method instanceof ReflectionMethod ? $method->getDeclaringClass() : NULL;
			if ($class && isset($flip[$class->getName()])) {
				continue;
			}
			$hash[] = [
				$name,
				$class ? PhpReflection::getUseStatements($method->getDeclaringClass()) : NULL,
				$method->getDocComment(),
				self::hashParameters($method),
				PHP_VERSION_ID >= 70000 ? $method->getReturnType() : NULL
			];
		}

		return md5(serialize($hash));
	}


	private static function hashParameters(\ReflectionFunctionAbstract $method)
	{
		$res = [];
		if (PHP_VERSION_ID < 70000 && $method->getNumberOfParameters() && $method->getFileName()) {
			$res[] = file($method->getFileName())[$method->getStartLine() - 1];
		}
		foreach ($method->getParameters() as $param) {
			$res[] = [
				$param->getName(),
				PHP_VERSION_ID >= 70000 ? PhpReflection::getParameterType($param) : NULL,
				$param->isVariadic(),
				$param->isDefaultValueAvailable()
					? ($param->isDefaultValueConstant() ? $param->getDefaultValueConstantName() : [$param->getDefaultValue()])
					: NULL
			];
		}
		return $res;
	}

}
