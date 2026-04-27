<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI;

use Nette;
use function array_flip, array_key_exists, array_keys, array_map, array_merge, array_values, class_exists, count, get_class_methods, implode, interface_exists, is_a, is_object, natsort, sprintf, str_replace, ucfirst;


/**
 * The dependency injection container default implementation.
 */
class Container
{
	/**
	 * @var mixed[]
	 * @deprecated use Container::getParameter() or getParameters()
	 */
	public $parameters = [];

	/** @var string[]  alias => service name */
	protected array $aliases = [];

	/** @var array<string, array<string, mixed>>  tag name => (service name => tag value) */
	protected array $tags = [];

	/** @var array<class-string, array<int, list<string>>>  type => (high/low/no => service names) */
	protected array $wiring = [];

	/** @var object[]  service name => instance */
	private array $instances = [];

	/** @var array<string, true>  circular reference detector */
	private array $creating;

	/** @var array<string, int> */
	private array $methods;

	/** @var array<string, \Closure(): object>  service name => factory */
	private array $factories = [];


	/** @param  mixed[]  $params */
	public function __construct(array $params = [])
	{
		$this->parameters = $params + $this->getStaticParameters();
		$this->methods = array_flip(get_class_methods($this));
	}


	/** @return mixed[] */
	public function getParameters(): array
	{
		return $this->parameters;
	}


	/**
	 * Returns a parameter value, loading it dynamically if not yet initialized.
	 */
	public function getParameter(string|int $key): mixed
	{
		if (!array_key_exists($key, $this->parameters)) {
			$this->parameters[$key] = $this->preventDeadLock("%$key%", fn() => $this->getDynamicParameter($key));
		}
		return $this->parameters[$key];
	}


	/** @return mixed[] */
	protected function getStaticParameters(): array
	{
		return [];
	}


	protected function getDynamicParameter(string|int $key): mixed
	{
		throw new Nette\InvalidStateException(sprintf("Parameter '%s' not found. Check if 'di › export › parameters' is enabled.", $key));
	}


	/**
	 * Adds the service or its factory to the container.
	 * @param  object  $service  service or its factory
	 */
	public function addService(string $name, object $service): static
	{
		$name = $this->aliases[$name] ?? $name;
		if (isset($this->instances[$name])) {
			throw new Nette\InvalidStateException(sprintf("Service '%s' already exists.", $name));
		}

		if ($service instanceof \Closure) {
			$rt = Nette\Utils\Type::fromReflection(new \ReflectionFunction($service));
			$type = $rt ? Helpers::ensureClassType($rt, 'return type of closure') : '';
		} else {
			$type = $service::class;
		}

		if (isset($this->methods[self::getMethodName($name)])
			&& ($expectedType = $this->getServiceType($name))
			&& !is_a($type, $expectedType, allow_string: true)
		) {
			throw new Nette\InvalidArgumentException(sprintf(
				"Service '%s' must be instance of %s, %s.",
				$name,
				$expectedType,
				$type ? "$type given" : 'add typehint to closure',
			));
		}

		if ($service instanceof \Closure) {
			$this->factories[$name] = $service;
		} else {
			$this->instances[$name] = $service;
		}

		return $this;
	}


	/**
	 * Removes a service instance from the container.
	 */
	public function removeService(string $name): void
	{
		$name = $this->aliases[$name] ?? $name;
		unset($this->instances[$name]);
	}


	/**
	 * Returns the service instance. If it has not been created yet, it creates it.
	 * @throws MissingServiceException
	 */
	public function getService(string $name): object
	{
		if (!isset($this->instances[$name])) {
			if (isset($this->aliases[$name])) {
				return $this->getService($this->aliases[$name]);
			}

			$this->instances[$name] = $this->createService($name);
		}

		return $this->instances[$name];
	}


	/**
	 * Returns the service instance. If it has not been created yet, it creates it.
	 * Alias for getService().
	 * @throws MissingServiceException
	 */
	public function getByName(string $name): object
	{
		return $this->getService($name);
	}


	/**
	 * Returns type of the service.
	 * @return class-string
	 * @throws MissingServiceException
	 */
	public function getServiceType(string $name): string
	{
		$method = self::getMethodName($name);
		if (isset($this->aliases[$name])) {
			return $this->getServiceType($this->aliases[$name]);

		} elseif (isset($this->methods[$method])) {
			return (string) (new \ReflectionMethod($this, $method))->getReturnType();

		} elseif ($cb = $this->factories[$name] ?? null) {
			return (string) (new \ReflectionFunction($cb))->getReturnType();

		} else {
			throw new MissingServiceException(sprintf("Type of service '%s' not known.", $name));
		}
	}


	/**
	 * Checks whether the service exists in the container.
	 */
	public function hasService(string $name): bool
	{
		$name = $this->aliases[$name] ?? $name;
		return isset($this->methods[self::getMethodName($name)]) || isset($this->instances[$name]) || isset($this->factories[$name]);
	}


	/**
	 * Has a service instance been created?
	 */
	public function isCreated(string $name): bool
	{
		if (!$this->hasService($name)) {
			throw new MissingServiceException(sprintf("Service '%s' not found.", $name));
		}

		$name = $this->aliases[$name] ?? $name;
		return isset($this->instances[$name]);
	}


	/**
	 * Creates new instance of the service.
	 * @throws MissingServiceException
	 */
	public function createService(string $name): object
	{
		$name = $this->aliases[$name] ?? $name;
		$method = self::getMethodName($name);
		if ($callback = ($this->factories[$name] ?? null)) {
			$service = $this->preventDeadLock($name, fn() => $callback());
		} elseif (isset($this->methods[$method])) {
			$service = $this->preventDeadLock($name, fn() => $this->$method());
		} else {
			throw new MissingServiceException(sprintf("Service '%s' not found.", $name));
		}

		if (!is_object($service)) {
			throw new Nette\UnexpectedValueException(sprintf(
				"Unable to create service '$name', value returned by %s is not object.",
				$callback instanceof \Closure ? 'closure' : "method $method()",
			));
		}

		return $service;
	}


	/**
	 * Returns an instance of the autowired service of the given type. If it has not been created yet, it creates it.
	 * @template T of object
	 * @param  class-string<T>  $type
	 * @return ($throw is true ? T : ?T)
	 * @throws MissingServiceException
	 */
	public function getByType(string $type, bool $throw = true): ?object
	{
		$type = Helpers::normalizeClass($type);
		if (!empty($this->wiring[$type][0])) {
			if (count($names = $this->wiring[$type][0]) === 1) {
				return $this->getService($names[0]);
			}

			natsort($names);
			throw new MissingServiceException(sprintf("Multiple services of type $type found: %s.", implode(', ', $names)));

		} elseif ($throw) {
			if (!class_exists($type) && !interface_exists($type)) {
				throw new MissingServiceException(sprintf("Service of type '%s' not found. Check the class name because it cannot be found.", $type));
			} elseif ($this->findByType($type)) {
				throw new MissingServiceException(sprintf("Service of type %s is not autowired or is missing in di\u{a0}›\u{a0}export\u{a0}›\u{a0}types.", $type));
			} else {
				throw new MissingServiceException(sprintf('Service of type %s not found. Did you add it to configuration file?', $type));
			}
		}

		return null;
	}


	/**
	 * Returns the names of autowired services of the given type.
	 * @param  class-string  $type
	 * @return list<string>
	 */
	public function findAutowired(string $type): array
	{
		$type = Helpers::normalizeClass($type);
		return array_merge($this->wiring[$type][0] ?? [], $this->wiring[$type][1] ?? []);
	}


	/**
	 * Returns the names of all services of the given type.
	 * @param  class-string  $type
	 * @return list<string>
	 */
	public function findByType(string $type): array
	{
		$type = Helpers::normalizeClass($type);
		return empty($this->wiring[$type])
			? []
			: array_merge(...array_values($this->wiring[$type]));
	}


	/**
	 * Returns the names of services with the given tag.
	 * @return array<string, mixed>  service name => tag value
	 */
	public function findByTag(string $tag): array
	{
		return $this->tags[$tag] ?? [];
	}


	/**
	 * Returns all registered services as a map of service name to type.
	 * Aliases are not included — use getAliases() separately.
	 * @return array<string, string>
	 */
	public function getServiceTypes(): array
	{
		$types = [];
		foreach (array_keys($this->methods) as $method) {
			if (strlen($method) > 13 && str_starts_with($method, 'createService')) {
				$name = lcfirst(str_replace('__', '.', substr($method, 13)));
				$types[$name] = (string) (new \ReflectionMethod($this, $method))->getReturnType();
			}
		}
		foreach ($this->factories as $name => $cb) {
			$types[$name] = (string) (new \ReflectionFunction($cb))->getReturnType();
		}
		return $types;
	}


	/**
	 * Returns the alias map: alias name => canonical service name.
	 * @return array<string, string>
	 */
	public function getAliases(): array
	{
		return $this->aliases;
	}


	/**
	 * Returns services that have already been instantiated, indexed by service name.
	 * @return array<string, object>
	 */
	public function getInstantiatedServices(): array
	{
		return $this->instances;
	}


	/**
	 * Returns the tags attached to the given service.
	 * @return array<string, mixed>  tag name => tag value
	 */
	public function getServiceTags(string $name): array
	{
		$name = $this->aliases[$name] ?? $name;
		$result = [];
		foreach ($this->tags as $tag => $services) {
			if (array_key_exists($name, $services)) {
				$result[$tag] = $services[$name];
			}
		}
		return $result;
	}


	/**
	 * Detects circular references and invokes the callback.
	 * @param  \Closure(): mixed  $callback
	 */
	private function preventDeadLock(string $key, \Closure $callback): mixed
	{
		if (isset($this->creating[$key])) {
			throw new Nette\InvalidStateException(sprintf('Circular reference detected for: %s.', implode(', ', array_keys($this->creating))));
		}
		try {
			$this->creating[$key] = true;
			return $callback();
		} finally {
			unset($this->creating[$key]);
		}
	}


	/********************* autowiring ****************d*g**/


	/**
	 * Creates an instance of the class and passes dependencies to the constructor using autowiring.
	 * @template T of object
	 * @param  class-string<T>  $class
	 * @param  array<mixed>  $args
	 * @return T
	 */
	public function createInstance(string $class, array $args = []): object
	{
		$rc = new \ReflectionClass($class);
		if (!$rc->isInstantiable()) {
			throw new ServiceCreationException(sprintf('Class %s is not instantiable.', $class));

		} elseif ($constructor = $rc->getConstructor()) {
			return $rc->newInstanceArgs($this->autowireArguments($constructor, $args));

		} elseif ($args) {
			throw new ServiceCreationException(sprintf('Unable to pass arguments, class %s has no constructor.', $class));
		}

		return new $class;
	}


	/**
	 * Calls all methods starting with 'inject' and passes dependencies to them via autowiring.
	 */
	public function callInjects(object $service): void
	{
		Extensions\InjectExtension::callInjects($this, $service);
	}


	/**
	 * Calls the method and passes dependencies to it via autowiring.
	 * @param  array<mixed>  $args
	 */
	public function callMethod(callable $function, array $args = []): mixed
	{
		return $function(...$this->autowireArguments(Nette\Utils\Callback::toReflection($function), $args));
	}


	/**
	 * @param  array<mixed>  $args
	 * @return array<mixed>
	 */
	private function autowireArguments(\ReflectionFunctionAbstract $function, array $args = []): array
	{
		return Resolver::autowireArguments($function, $args, fn(string $type, bool $single) => $single
				? $this->getByType($type)
				: array_map($this->getService(...), $this->findAutowired($type)));
	}


	/**
	 * Returns the method name for creating a service.
	 */
	final public static function getMethodName(string $name): string
	{
		if ($name === '') {
			throw new Nette\InvalidArgumentException('Service name must be a non-empty string.');
		}

		return 'createService' . str_replace('.', '__', ucfirst($name));
	}


	public function initialize(): void
	{
	}
}
