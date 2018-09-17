<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI;

use Nette;
use Nette\PhpGenerator\Helpers as PhpHelpers;
use Nette\Utils\Reflection;
use Nette\Utils\Strings;
use Nette\Utils\Validators;
use ReflectionClass;


/**
 * Container builder.
 */
class ContainerBuilder
{
	use Nette\SmartObject;

	public const
		THIS_SERVICE = 'self',
		THIS_CONTAINER = 'container';

	/** @var array */
	public $parameters = [];

	/** @var ServiceDefinition[] */
	private $definitions = [];

	/** @var array of alias => service */
	private $aliases = [];

	/** @var array for auto-wiring */
	private $classList = [];

	/** @var bool */
	private $classListNeedsRefresh = true;

	/** @var string[] of classes excluded from auto-wiring */
	private $excludedClasses = [];

	/** @var array */
	private $dependencies = [];

	/** @var string */
	private $currentService;


	/**
	 * Adds new service definition.
	 */
	public function addDefinition(string $name, ServiceDefinition $definition = null): ServiceDefinition
	{
		$this->classListNeedsRefresh = true;
		if (!$name) { // builder is not ready for falsy names such as '0'
			throw new Nette\InvalidArgumentException(sprintf('Service name must be a non-empty string, %s given.', gettype($name)));
		}
		$name = $this->aliases[$name] ?? $name;
		if (isset($this->definitions[$name])) {
			throw new Nette\InvalidStateException("Service '$name' has already been added.");
		}
		if (!$definition) {
			$definition = new ServiceDefinition;
		}
		$definition->setNotifier(function () {
			$this->classListNeedsRefresh = true;
		});
		return $this->definitions[$name] = $definition;
	}


	/**
	 * Removes the specified service definition.
	 */
	public function removeDefinition(string $name): void
	{
		$this->classListNeedsRefresh = true;
		$name = $this->aliases[$name] ?? $name;
		unset($this->definitions[$name]);
	}


	/**
	 * Gets the service definition.
	 */
	public function getDefinition(string $name): ServiceDefinition
	{
		$service = $this->aliases[$name] ?? $name;
		if (!isset($this->definitions[$service])) {
			throw new MissingServiceException("Service '$name' not found.");
		}
		return $this->definitions[$service];
	}


	/**
	 * Gets all service definitions.
	 * @return ServiceDefinition[]
	 */
	public function getDefinitions(): array
	{
		return $this->definitions;
	}


	/**
	 * Does the service definition or alias exist?
	 */
	public function hasDefinition(string $name): bool
	{
		$name = $this->aliases[$name] ?? $name;
		return isset($this->definitions[$name]);
	}


	public function addAlias(string $alias, string $service): void
	{
		if (!$alias) { // builder is not ready for falsy names such as '0'
			throw new Nette\InvalidArgumentException(sprintf('Alias name must be a non-empty string, %s given.', gettype($alias)));

		} elseif (!$service) { // builder is not ready for falsy names such as '0'
			throw new Nette\InvalidArgumentException(sprintf('Service name must be a non-empty string, %s given.', gettype($service)));

		} elseif (isset($this->aliases[$alias])) {
			throw new Nette\InvalidStateException("Alias '$alias' has already been added.");

		} elseif (isset($this->definitions[$alias])) {
			throw new Nette\InvalidStateException("Service '$alias' has already been added.");
		}
		$this->aliases[$alias] = $service;
	}


	/**
	 * Removes the specified alias.
	 */
	public function removeAlias(string $alias): void
	{
		unset($this->aliases[$alias]);
	}


	/**
	 * Gets all service aliases.
	 */
	public function getAliases(): array
	{
		return $this->aliases;
	}


	/**
	 * @param  string[]  $types
	 * @return static
	 */
	public function addExcludedClasses(array $types)
	{
		foreach ($types as $type) {
			if (class_exists($type) || interface_exists($type)) {
				$type = Helpers::normalizeClass($type);
				$this->excludedClasses += class_parents($type) + class_implements($type) + [$type => $type];
			}
		}
		return $this;
	}


	/********************* class resolving ****************d*g**/


	/**
	 * Resolves service name by type.
	 * @param  bool  $throw exception if service doesn't exist?
	 * @throws ServiceCreationException
	 */
	public function getByType(string $type, bool $throw = false): ?string
	{
		$type = Helpers::normalizeClass($type);

		if (
			$this->currentService !== null
			&& is_a($this->definitions[$this->currentService]->getType(), $type, true)
		) {
			return $this->currentService;
		}

		$types = $this->getClassList();
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
	 * Gets the service definition of the specified type.
	 */
	public function getDefinitionByType(string $type): ServiceDefinition
	{
		return $this->getDefinition($this->getByType($type, true));
	}


	/**
	 * Gets the service names and definitions of the specified type.
	 * @return ServiceDefinition[]
	 */
	public function findByType(string $type): array
	{
		$type = Helpers::normalizeClass($type);
		$found = [];
		$types = $this->getClassList();
		if (!empty($types[$type])) {
			foreach (array_merge(...array_values($types[$type])) as $name) {
				$found[$name] = $this->definitions[$name];
			}
		}
		return $found;
	}


	/**
	 * Gets the service objects of the specified tag.
	 * @return array of [service name => tag attributes]
	 */
	public function findByTag(string $tag): array
	{
		$found = [];
		foreach ($this->definitions as $name => $def) {
			if (($tmp = $def->getTag($tag)) !== null) {
				$found[$name] = $tmp;
			}
		}
		return $found;
	}


	/**
	 * @internal
	 */
	public function getClassList(): array
	{
		if ($this->classList !== false && $this->classListNeedsRefresh) {
			$this->prepareClassList();
			$this->classListNeedsRefresh = false;
		}
		return $this->classList ?: [];
	}


	/**
	 * Generates $dependencies, $classList and normalizes class names.
	 * @internal
	 */
	public function prepareClassList(): void
	{
		unset($this->definitions[self::THIS_CONTAINER]);
		$this->addDefinition(self::THIS_CONTAINER)->setType(Container::class);

		$this->classList = false;

		foreach ($this->definitions as $name => $def) {
			// prepare generated factories
			if ($def->getImplement()) {
				$this->resolveImplement($def, $name);
			}

			if ($def->isDynamic()) {
				if (!$def->getType()) {
					throw new ServiceCreationException("Type is missing in definition of service '$name'.");
				}
				$def->setFactory(null);
				continue;
			}

			// complete class-factory pairs
			if (!$def->getEntity()) {
				if (!$def->getType()) {
					throw new ServiceCreationException("Factory and type are missing in definition of service '$name'.");
				}
				$def->setFactory($def->getType(), ($factory = $def->getFactory()) ? $factory->arguments : []);
			}

			// auto-disable autowiring for aliases
			if (
				$def->getAutowired() === true
				&& ($alias = $this->getServiceName($def->getFactory()->getEntity()))
				&& (!$def->getImplement() || (!Strings::contains($alias, '\\') && $this->definitions[$alias]->getImplement()))
			) {
				$def->setAutowired(false);
			}
		}

		// resolve and check classes
		foreach ($this->definitions as $name => $def) {
			$this->resolveServiceType($name);
		}

		//  build auto-wiring list
		$this->classList = $preferred = [];
		foreach ($this->definitions as $name => $def) {
			if ($type = $def->getImplement() ?: $def->getType()) {
				$defAutowired = $def->getAutowired();
				if (is_array($defAutowired)) {
					foreach ($defAutowired as $k => $autowiredType) {
						if ($autowiredType === self::THIS_SERVICE) {
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
	}


	private function resolveImplement(ServiceDefinition $def, $name): void
	{
		$interface = $def->getImplement();
		if (!interface_exists($interface)) {
			throw new ServiceCreationException("Interface $interface used in service '$name' not found.");
		}
		$interface = Helpers::normalizeClass($interface);
		$def->setImplement($interface);

		$rc = new ReflectionClass($interface);
		$this->addDependency($rc);
		$method = $rc->hasMethod('create')
			? $rc->getMethod('create')
			: ($rc->hasMethod('get') ? $rc->getMethod('get') : null);

		if (count($rc->getMethods()) !== 1 || !$method || $method->isStatic()) {
			throw new ServiceCreationException("Interface $interface used in service '$name' must have just one non-static method create() or get().");
		}
		$def->setImplementMode($rc->hasMethod('create') ? $def::IMPLEMENT_MODE_CREATE : $def::IMPLEMENT_MODE_GET);
		$methodName = Reflection::toString($method) . '()';

		if (!$def->getType() && !$def->getEntity()) {
			$returnType = Helpers::getReturnType($method);
			if (!$returnType) {
				throw new ServiceCreationException("Method $methodName used in service '$name' has not return type hint or annotation @return.");
			} elseif (!class_exists($returnType)) {
				throw new ServiceCreationException("Check a type hint or annotation @return of the $methodName method used in service '$name', class '$returnType' cannot be found.");
			}
			$def->setType($returnType);
		}

		if ($rc->hasMethod('get')) {
			if ($method->getParameters()) {
				throw new ServiceCreationException("Method $methodName used in service '$name' must have no arguments.");
			} elseif ($def->getSetup()) {
				throw new ServiceCreationException("Service accessor '$name' must have no setup.");
			}
			if (!$def->getEntity()) {
				$def->setFactory('@\\' . ltrim($def->getType(), '\\'));
			} elseif (!$this->getServiceName($def->getFactory()->getEntity())) {
				throw new ServiceCreationException("Invalid factory in service '$name' definition.");
			}
		}

		if (!$def->parameters) {
			$ctorParams = [];
			if (!$def->getEntity()) {
				$def->setFactory($def->getType(), $def->getFactory() ? $def->getFactory()->arguments : []);
			}
			if (
				($class = $this->resolveEntityType($def->getFactory(), [$name => 1]))
				&& ($ctor = (new ReflectionClass($class))->getConstructor())
			) {
				foreach ($ctor->getParameters() as $param) {
					$ctorParams[$param->getName()] = $param;
				}
			}

			foreach ($method->getParameters() as $param) {
				$hint = Reflection::getParameterType($param);
				if (isset($ctorParams[$param->getName()])) {
					$arg = $ctorParams[$param->getName()];
					if ($hint !== Reflection::getParameterType($arg)) {
						throw new ServiceCreationException("Type hint for \${$param->getName()} in $methodName doesn't match type hint in $class constructor.");
					}
					$def->getFactory()->arguments[$arg->getPosition()] = self::literal('$' . $arg->getName());
				} elseif (!$def->getSetup()) {
					$hint = Nette\Utils\ObjectHelpers::getSuggestion(array_keys($ctorParams), $param->getName());
					throw new ServiceCreationException("Unused parameter \${$param->getName()} when implementing method $methodName" . ($hint ? ", did you mean \${$hint}?" : '.'));
				}
				$nullable = $hint && $param->allowsNull() && (!$param->isDefaultValueAvailable() || $param->getDefaultValue() !== null);
				$paramDef = ($nullable ? '?' : '') . $hint . ' ' . $param->getName();
				if ($param->isDefaultValueAvailable()) {
					$def->parameters[$paramDef] = Reflection::getParameterDefaultValue($param);
				} else {
					$def->parameters[] = $paramDef;
				}
			}
		}
	}


	private function resolveServiceType($name, array $recursive = []): ?string
	{
		if (isset($recursive[$name])) {
			throw new ServiceCreationException(sprintf('Circular reference detected for services: %s.', implode(', ', array_keys($recursive))));
		}
		$recursive[$name] = true;

		$def = $this->definitions[$name];
		$factoryClass = $def->getFactory() ? $this->resolveEntityType($def->getFactory()->getEntity(), $recursive) : null; // call always to check entities
		if ($type = $def->getType() ?: $factoryClass) {
			if (!class_exists($type) && !interface_exists($type)) {
				throw new ServiceCreationException("Class or interface '$type' used in service '$name' not found.");
			}
			$type = Helpers::normalizeClass($type);
			$def->setType($type);
			if (count($recursive) === 1) {
				$this->addDependency(new ReflectionClass($factoryClass ?: $type));
			}

		} elseif ($def->getAutowired()) {
			throw new ServiceCreationException("Unknown type of service '$name', declare return type of factory method (for PHP 5 use annotation @return)");
		}
		return $type;
	}


	private function resolveEntityType($entity, array $recursive = []): ?string
	{
		$entity = $this->normalizeEntity($entity instanceof Statement ? $entity->getEntity() : $entity);
		$serviceName = current(array_slice(array_keys($recursive), -1));

		if (is_array($entity)) {
			if (($service = $this->getServiceName($entity[0])) || $entity[0] instanceof Statement) {
				$entity[0] = $this->resolveEntityType($entity[0], $recursive);
				if (!$entity[0]) {
					return null;
				} elseif (isset($this->definitions[$service]) && $this->definitions[$service]->getImplement()) { // @Implement::create
					return $entity[1] === 'create' ? $this->resolveServiceType($service, $recursive) : null;
				}
			}

			try {
				$reflection = Nette\Utils\Callback::toReflection($entity[0] === '' ? $entity[1] : $entity);
				$refClass = $reflection instanceof \ReflectionMethod ? $reflection->getDeclaringClass() : null;
			} catch (\ReflectionException $e) {
			}

			if (isset($e) || ($refClass && (!$reflection->isPublic()
				|| ($refClass->isTrait() && !$reflection->isStatic())
			))) {
				throw new ServiceCreationException(sprintf("Method %s() used in service '%s' is not callable.", Nette\Utils\Callback::toString($entity), $serviceName), 0, $e ?? null);
			}
			$this->addDependency($reflection);

			$type = Helpers::getReturnType($reflection);
			if ($type && !class_exists($type) && !interface_exists($type)) {
				throw new ServiceCreationException(sprintf("Class or interface '%s' not found. Is return type of %s() used in service '%s' correct?", $type, Nette\Utils\Callback::toString($entity), $serviceName));
			}
			return $type;

		} elseif ($service = $this->getServiceName($entity)) { // alias or factory
			if (Strings::contains($service, '\\')) { // @\Class
				return $service;
			}
			return $this->definitions[$service]->getImplement()
				?: $this->definitions[$service]->getType()
				?: $this->resolveServiceType($service, $recursive);

		} elseif (is_string($entity)) { // class
			if (!class_exists($entity)) {
				throw new ServiceCreationException("Class $entity used in service '$serviceName' not found.");
			}
			return $entity;
		}
		return null;
	}


	public function complete(): void
	{
		$this->prepareClassList();

		foreach ($this->definitions as $name => $def) {
			if ($def->isDynamic()) {
				continue;
			}

			$this->currentService = null;
			$entity = $def->getFactory()->getEntity();
			$serviceRef = $this->getServiceName($entity);
			$factory = $serviceRef && !$def->getFactory()->arguments && !$def->getSetup() && $def->getImplementMode() !== $def::IMPLEMENT_MODE_CREATE
				? new Statement(['@' . self::THIS_CONTAINER, 'getService'], [$serviceRef])
				: $def->getFactory();

			try {
				$def->setFactory($this->completeStatement($factory));
				$this->classListNeedsRefresh = false;

				$this->currentService = $name;
				$setups = $def->getSetup();
				foreach ($setups as &$setup) {
					if (is_string($setup->getEntity()) && strpbrk($setup->getEntity(), ':@?\\') === false) { // auto-prepend @self
						$setup = new Statement(['@' . $name, $setup->getEntity()], $setup->arguments);
					}
					$setup = $this->completeStatement($setup);
				}
				$def->setSetup($setups);

			} catch (\Exception $e) {
				$message = "Service '$name' (type of {$def->getType()}): " . $e->getMessage();
				throw $e instanceof ServiceCreationException
					? $e->setMessage($message)
					: new ServiceCreationException($message, 0, $e);

			} finally {
				$this->currentService = null;
			}
		}
	}


	public function completeStatement(Statement $statement): Statement
	{
		$entity = $this->normalizeEntity($statement->getEntity());
		$arguments = $statement->arguments;

		if (is_string($entity) && Strings::contains($entity, '?')) { // PHP literal

		} elseif ($service = $this->getServiceName($entity)) { // factory calling
			$params = [];
			foreach ($this->definitions[$service]->parameters as $k => $v) {
				$params[] = preg_replace('#\w+\z#', '\$$0', (is_int($k) ? $v : $k)) . (is_int($k) ? '' : ' = ' . PhpHelpers::dump($v));
			}
			$rm = new \ReflectionFunction(eval('return function(' . implode(', ', $params) . ') {};'));
			$arguments = Helpers::autowireArguments($rm, $arguments, $this);
			$entity = '@' . $service;

		} elseif ($entity === 'not') { // operator

		} elseif (is_string($entity)) { // class name
			if (!class_exists($entity)) {
				throw new ServiceCreationException("Class $entity not found.");
			} elseif ((new ReflectionClass($entity))->isAbstract()) {
				throw new ServiceCreationException("Class $entity is abstract.");
			} elseif (($rm = (new ReflectionClass($entity))->getConstructor()) !== null && !$rm->isPublic()) {
				$visibility = $rm->isProtected() ? 'protected' : 'private';
				throw new ServiceCreationException("Class $entity has $visibility constructor.");
			} elseif ($constructor = (new ReflectionClass($entity))->getConstructor()) {
				$this->addDependency($constructor);
				$arguments = Helpers::autowireArguments($constructor, $arguments, $this);
			} elseif ($arguments) {
				throw new ServiceCreationException("Unable to pass arguments, class $entity has no constructor.");
			}

		} elseif (!Nette\Utils\Arrays::isList($entity) || count($entity) !== 2) {
			throw new ServiceCreationException(sprintf('Expected class, method or property, %s given.', PhpHelpers::dump($entity)));

		} elseif (!preg_match('#^\$?(\\\\?' . PhpHelpers::PHP_IDENT . ')+(\[\])?\z#', $entity[1])) {
			throw new ServiceCreationException("Expected function, method or property name, '$entity[1]' given.");

		} elseif ($entity[0] === '') { // globalFunc
			if (!Nette\Utils\Arrays::isList($arguments)) {
				throw new ServiceCreationException("Unable to pass specified arguments to $entity[0].");
			} elseif (!function_exists($entity[1])) {
				throw new ServiceCreationException("Function $entity[1] doesn't exist.");
			}

			$rf = new \ReflectionFunction($entity[1]);
			$this->addDependency($rf);
			$arguments = Helpers::autowireArguments($rf, $arguments, $this);

		} else {
			if ($entity[0] instanceof Statement) {
				$entity[0] = $this->completeStatement($entity[0]);
			} elseif ($service = $this->getServiceName($entity[0])) { // service method
				$entity[0] = '@' . $service;
			}

			if ($entity[1][0] === '$') { // property getter, setter or appender
				Validators::assert($arguments, 'list:0..1', "setup arguments for '" . Nette\Utils\Callback::toString($entity) . "'");
				if (!$arguments && substr($entity[1], -2) === '[]') {
					throw new ServiceCreationException("Missing argument for $entity[1].");
				}
			} elseif (
				$type = empty($service) || $entity[1] === 'create'
					? $this->resolveEntityType($entity[0])
					: $this->definitions[$service]->getType()
			) {
				$arguments = $this->autowireArguments($type, $entity[1], $arguments);
			}
		}

		try {
			array_walk_recursive($arguments, function (&$val): void {
				if ($val instanceof Statement) {
					$val = $this->completeStatement($val);

				} elseif ($val instanceof ServiceDefinition) {
					$val = '@' . current(array_keys($this->getDefinitions(), $val, true));

				} elseif (is_string($val) && strlen($val) > 1 && $val[0] === '@' && $val[1] !== '@') {
					$pair = explode('::', $val, 2);
					$name = $this->getServiceName($pair[0]);
					if (!isset($pair[1])) { // @service
						$val = '@' . $name;
					} elseif (preg_match('#^[A-Z][A-Z0-9_]*\z#', $pair[1], $m)) { // @service::CONSTANT
						$val = self::literal($this->getDefinition($name)->getType() . '::' . $pair[1]);
					} else { // @service::property
						$val = new Statement(['@' . $name, '$' . $pair[1]]);
					}
				}
			});

		} catch (ServiceCreationException $e) {
			if ((is_string($entity) || is_array($entity)) && !strpos($e->getMessage(), ' (used in')) {
				$desc = is_string($entity)
					? $entity . '::__construct'
					: (is_string($entity[0]) ? ($entity[0] . '::') : 'method ') . $entity[1];
				$e->setMessage($e->getMessage() . " (used in $desc)");
			}
			throw $e;
		}

		return new Statement($entity, $arguments);
	}


	/**
	 * Adds item to the list of dependencies.
	 * @param  ReflectionClass|\ReflectionFunctionAbstract|string  $dep
	 * @return static
	 * @internal
	 */
	public function addDependency($dep)
	{
		$this->dependencies[] = $dep;
		return $this;
	}


	/**
	 * Returns the list of dependencies.
	 */
	public function getDependencies(): array
	{
		return $this->dependencies;
	}


	public static function literal(string $code, array $args = null): Nette\PhpGenerator\PhpLiteral
	{
		return new Nette\PhpGenerator\PhpLiteral($args === null ? $code : PhpHelpers::formatArgs($code, $args));
	}


	/**
	 * @return string|array  Class, @service, [Class, member], [@service, member], [, globalFunc], [Statement, member]
	 * @internal
	 */
	public function normalizeEntity($entity)
	{
		if (is_string($entity) && Strings::contains($entity, '::') && !Strings::contains($entity, '?')) { // Class::method -> [Class, method]
			$entity = explode('::', $entity);
		}

		if (is_array($entity) && $entity[0] instanceof ServiceDefinition) { // [ServiceDefinition, ...] -> [@serviceName, ...]
			$entity[0] = '@' . current(array_keys($this->definitions, $entity[0], true));

		} elseif ($entity instanceof ServiceDefinition) { // ServiceDefinition -> @serviceName
			$entity = '@' . current(array_keys($this->definitions, $entity, true));
		}
		return $entity;
	}


	/**
	 * Converts @service or @\Class -> service name and checks its existence.
	 * @internal
	 */
	public function getServiceName($arg): ?string
	{
		if (!is_string($arg) || !preg_match('#^@[\w\\\\.][^:]*\z#', $arg)) {
			return null;
		}
		$service = substr($arg, 1);
		if ($service === self::THIS_SERVICE) {
			$service = $this->currentService;
		}
		if (Strings::contains($service, '\\')) {
			if ($this->classList === false) { // may be disabled by prepareClassList
				return $service;
			}
			$res = $this->getByType($service);
			if (!$res) {
				throw new ServiceCreationException("Reference to missing service of type $service.");
			}
			return $res;
		}
		$service = $this->aliases[$service] ?? $service;
		if (!isset($this->definitions[$service])) {
			throw new ServiceCreationException("Reference to missing service '$service'.");
		}
		return $service;
	}


	/**
	 * Creates a list of arguments using autowiring.
	 * @internal
	 */
	public function autowireArguments(string $class, string $method, array $arguments): array
	{
		$rc = new ReflectionClass($class);
		if (!$rc->hasMethod($method)) {
			if (!Nette\Utils\Arrays::isList($arguments)) {
				throw new ServiceCreationException("Unable to pass specified arguments to $class::$method().");
			}
			return $arguments;
		}

		$rm = $rc->getMethod($method);
		if (!$rm->isPublic()) {
			throw new ServiceCreationException("$class::$method() is not callable.");
		}
		$this->addDependency($rm);
		return Helpers::autowireArguments($rm, $arguments, $this);
	}


	/** @deprecated */
	public function formatPhp(string $statement, array $args): string
	{
		array_walk_recursive($args, function (&$val) {
			if ($val instanceof Statement) {
				$val = $this->completeStatement($val);

			} elseif ($val instanceof ServiceDefinition) {
				$val = '@' . current(array_keys($this->getDefinitions(), $val, true));
			}
		});
		return (new PhpGenerator($this))->formatPhp($statement, $args);
	}
}
