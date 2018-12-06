<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI;

use Nette;


/**
 * The dependency injection container default implementation.
 */
class Container
{
	use Nette\SmartObject;

	/** @var array  user parameters */
	public $parameters = [];

	/** @var string[] */
	protected $services = [];

	/** @var string[] */
	protected $aliases = [];

	/** @var array[] */
	protected $types = [];

	/** @var array[] */
	protected $tags = [];

	/** @var object[]  storage for shared objects */
	private $registry = [];

	/** @var array circular reference detector */
	private $creating;


	public function __construct(array $params = [])
	{
		$this->parameters = $params + $this->parameters;
	}


	public function getParameters(): array
	{
		return $this->parameters;
	}


	/**
	 * Adds the service to the container.
	 * @param  object  $service
	 * @return static
	 */
	public function addService(string $name, $service)
	{
		if (!$name) {
			throw new Nette\InvalidArgumentException(sprintf('Service name must be a non-empty string, %s given.', gettype($name)));
		}
		$name = $this->aliases[$name] ?? $name;
		if (isset($this->registry[$name])) {
			throw new Nette\InvalidStateException("Service '$name' already exists.");

		} elseif (!is_object($service)) {
			throw new Nette\InvalidArgumentException(sprintf("Service '%s' must be a object, %s given.", $name, gettype($service)));

		} elseif (isset($this->services[$name]) && !$service instanceof $this->services[$name]) {
			throw new Nette\InvalidArgumentException(sprintf("Service '%s' must be instance of %s, %s given.", $name, $this->services[$name], get_class($service)));
		}

		$this->registry[$name] = $service;
		return $this;
	}


	/**
	 * Removes the service from the container.
	 */
	public function removeService(string $name): void
	{
		$name = $this->aliases[$name] ?? $name;
		unset($this->registry[$name]);
	}


	/**
	 * Gets the service object by name.
	 * @return object
	 * @throws MissingServiceException
	 */
	public function getService(string $name)
	{
		if (!isset($this->registry[$name])) {
			if (isset($this->aliases[$name])) {
				return $this->getService($this->aliases[$name]);
			}
			$this->registry[$name] = $this->createService($name);
		}
		return $this->registry[$name];
	}


	/**
	 * Gets the service type by name.
	 * @throws MissingServiceException
	 */
	public function getServiceType(string $name): string
	{
		if (isset($this->aliases[$name])) {
			return $this->getServiceType($this->aliases[$name]);

		} elseif (isset($this->services[$name])) {
			return $this->services[$name];

		} else {
			throw new MissingServiceException("Service '$name' not found.");
		}
	}


	/**
	 * Does the service exist?
	 */
	public function hasService(string $name): bool
	{
		$name = $this->aliases[$name] ?? $name;
		return isset($this->registry[$name])
			|| (method_exists($this, $method = self::getMethodName($name))
				&& (new \ReflectionMethod($this, $method))->getName() === $method);
	}


	/**
	 * Is the service created?
	 */
	public function isCreated(string $name): bool
	{
		if (!$this->hasService($name)) {
			throw new MissingServiceException("Service '$name' not found.");
		}
		$name = $this->aliases[$name] ?? $name;
		return isset($this->registry[$name]);
	}


	/**
	 * Creates new instance of the service.
	 * @return object
	 * @throws MissingServiceException
	 */
	public function createService(string $name, array $args = [])
	{
		$name = $this->aliases[$name] ?? $name;
		$method = self::getMethodName($name);
		if (isset($this->creating[$name])) {
			throw new Nette\InvalidStateException(sprintf('Circular reference detected for services: %s.', implode(', ', array_keys($this->creating))));

		} elseif (!method_exists($this, $method) || (new \ReflectionMethod($this, $method))->getName() !== $method) {
			throw new MissingServiceException("Service '$name' not found.");
		}

		try {
			$this->creating[$name] = true;
			$service = $this->$method(...$args);

		} finally {
			unset($this->creating[$name]);
		}

		if (!is_object($service)) {
			throw new Nette\UnexpectedValueException("Unable to create service '$name', value returned by method $method() is not object.");
		}

		return $service;
	}


	/**
	 * Resolves service by type.
	 * @param  bool  $throw  exception if service doesn't exist?
	 * @return object|null  service
	 * @throws MissingServiceException
	 */
	public function getByType(string $type, bool $throw = true)
	{
		$type = Helpers::normalizeClass($type);
		if (!empty($this->types[$type][true])) {
			if (count($names = $this->types[$type][true]) === 1) {
				return $this->getService($names[0]);
			}
			natsort($names);
			throw new MissingServiceException("Multiple services of type $type found: " . implode(', ', $names) . '.');

		} elseif ($throw) {
			throw new MissingServiceException("Service of type $type not found.");
		}
		return null;
	}


	/**
	 * Gets the service names of the specified type.
	 * @return string[]
	 */
	public function findByType(string $type): array
	{
		$type = Helpers::normalizeClass($type);
		return empty($this->types[$type])
			? []
			: array_merge(...array_values($this->types[$type]));
	}


	/**
	 * Gets the service names of the specified tag.
	 * @return array of [service name => tag attributes]
	 */
	public function findByTag(string $tag): array
	{
		return $this->tags[$tag] ?? [];
	}


	/********************* autowiring ****************d*g**/


	/**
	 * Creates new instance using autowiring.
	 * @return object
	 * @throws Nette\InvalidArgumentException
	 */
	public function createInstance(string $class, array $args = [])
	{
		$rc = new \ReflectionClass($class);
		if (!$rc->isInstantiable()) {
			throw new ServiceCreationException("Class $class is not instantiable.");

		} elseif ($constructor = $rc->getConstructor()) {
			return $rc->newInstanceArgs(Autowiring::completeArguments($constructor, $args, $this));

		} elseif ($args) {
			throw new ServiceCreationException("Unable to pass arguments, class $class has no constructor.");
		}
		return new $class;
	}


	/**
	 * Calls all methods starting with with "inject" using autowiring.
	 * @param  object  $service
	 */
	public function callInjects($service): void
	{
		Extensions\InjectExtension::callInjects($this, $service);
	}


	/**
	 * Calls method using autowiring.
	 * @return mixed
	 */
	public function callMethod(callable $function, array $args = [])
	{
		return $function(...Autowiring::completeArguments(Nette\Utils\Callback::toReflection($function), $args, $this));
	}


	public static function getMethodName(string $name): string
	{
		$uname = ucfirst($name);
		return 'createService' . ($name === $uname ? '__' : '') . str_replace('.', '__', $uname);
	}
}
