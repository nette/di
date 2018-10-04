<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI;

use Nette;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\PhpGenerator\Helpers as PhpHelpers;
use Nette\Utils\Reflection;
use Nette\Utils\Strings;
use Nette\Utils\Validators;
use ReflectionClass;


/**
 * Services resolver
 * @internal
 */
class Resolver
{
	use Nette\SmartObject;

	/** @var ContainerBuilder */
	private $builder;

	/** @var string|null */
	private $currentService;


	public function __construct(ContainerBuilder $builder)
	{
		$this->builder = $builder;
	}


	public function getContainerBuilder(): ContainerBuilder
	{
		return $this->builder;
	}


	public function resolveDefinition(ServiceDefinition $def): void
	{
		$name = $def->getName();

		// prepare generated factories
		if ($def->getImplement()) {
			$this->resolveImplement($def);
		}

		if ($def->isDynamic()) {
			if (!$def->getType()) {
				throw new ServiceCreationException("Type is missing in definition of service '$name'.");
			}
			$def->setFactory(null);
			return;
		}

		// complete class-factory pairs
		if (!$def->getEntity()) {
			if (!$def->getType()) {
				throw new ServiceCreationException("Factory and type are missing in definition of service '$name'.");
			}
			$def->setFactory($def->getType(), ($factory = $def->getFactory()) ? $factory->arguments : []);
		}

		// auto-disable autowiring for aliases
		$definitions = $this->builder->getDefinitions();
		if (
			$def->getAutowired() === true
			&& ($alias = $this->getServiceName($def->getFactory()->getEntity()))
			&& (!$def->getImplement() || (!Strings::contains($alias, '\\') && $definitions[$alias]->getImplement()))
		) {
			$def->setAutowired(false);
		}

		$this->resolveServiceType($name);
	}


	private function resolveImplement(ServiceDefinition $def): void
	{
		$name = $def->getName();
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
					$argHint = Reflection::getParameterType($arg);
					if ($hint !== $argHint && !is_a($hint, $argHint, true)) {
						throw new ServiceCreationException("Type hint for \${$param->getName()} in $methodName doesn't match type hint in $class constructor.");
					}
					$def->getFactory()->arguments[$arg->getPosition()] = ContainerBuilder::literal('$' . $arg->getName());
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


	private function resolveServiceType(string $name, array $recursive = []): ?string
	{
		if (isset($recursive[$name])) {
			throw new ServiceCreationException(sprintf('Circular reference detected for services: %s.', implode(', ', array_keys($recursive))));
		}
		$recursive[$name] = true;

		$def = $this->builder->getDefinition((string) $name);
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
		$definitions = $this->builder->getDefinitions();
		$entity = $this->normalizeEntity($entity instanceof Statement ? $entity->getEntity() : $entity);
		$serviceName = current(array_slice(array_keys($recursive), -1));

		if (is_array($entity)) {
			if (($service = $this->getServiceName($entity[0])) || $entity[0] instanceof Statement) {
				$entity[0] = $this->resolveEntityType($entity[0], $recursive);
				if (!$entity[0]) {
					return null;
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
			return $definitions[$service]->getImplement()
				?: $definitions[$service]->getType()
				?: $this->resolveServiceType($service, $recursive);

		} elseif (is_string($entity)) { // class
			if (!class_exists($entity)) {
				throw new ServiceCreationException("Class $entity used in service '$serviceName' not found.");
			}
			return $entity;
		}
		return null;
	}


	public function completeDefinition(ServiceDefinition $def): void
	{
		if ($def->isDynamic()) {
			return;
		}

		$this->currentService = null;
		$entity = $def->getFactory()->getEntity();
		$serviceRef = $this->getServiceName($entity);
		$factory = $serviceRef && !$def->getFactory()->arguments && !$def->getSetup() && $def->getImplementMode() !== $def::IMPLEMENT_MODE_CREATE
			? new Statement(['@' . ContainerBuilder::THIS_CONTAINER, 'getService'], [$serviceRef])
			: $def->getFactory();

		try {
			$def->setFactory($this->completeStatement($factory));

			$this->currentService = $def->getName();
			$setups = $def->getSetup();
			foreach ($setups as &$setup) {
				if (is_string($setup->getEntity()) && strpbrk($setup->getEntity(), ':@?\\') === false) { // auto-prepend @self
					$setup = new Statement(['@self', $setup->getEntity()], $setup->arguments);
				}
				$setup = $this->completeStatement($setup);
			}
			$def->setSetup($setups);

		} catch (\Exception $e) {
			$message = "Service '{$def->getName()}' (type of {$def->getType()}): " . $e->getMessage();
			throw $e instanceof ServiceCreationException
				? $e->setMessage($message)
				: new ServiceCreationException($message, 0, $e);

		} finally {
			$this->currentService = null;
		}
	}


	public function completeStatement(Statement $statement): Statement
	{
		$entity = $this->normalizeEntity($statement->getEntity());
		$arguments = $statement->arguments;
		$definitions = $this->builder->getDefinitions();

		if (is_string($entity) && Strings::contains($entity, '?')) { // PHP literal

		} elseif ($service = $this->getServiceName($entity)) { // factory calling
			$params = [];
			foreach ($definitions[$service]->parameters as $k => $v) {
				$params[] = preg_replace('#\w+\z#', '\$$0', (is_int($k) ? $v : $k)) . (is_int($k) ? '' : ' = ' . PhpHelpers::dump($v));
			}
			$rm = new \ReflectionFunction(eval('return function(' . implode(', ', $params) . ') {};'));
			$arguments = Autowiring::completeArguments($rm, $arguments, $this);
			$entity = '@' . $service;

		} elseif ($entity === 'not') { // special

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
				$arguments = Autowiring::completeArguments($constructor, $arguments, $this);
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
			$arguments = Autowiring::completeArguments($rf, $arguments, $this);

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
					: $definitions[$service]->getType()
			) {
				$arguments = $this->autowireArguments($type, $entity[1], $arguments);
			}
		}

		try {
			array_walk_recursive($arguments, function (&$val): void {
				if ($val instanceof Statement) {
					$val = $this->completeStatement($val);

				} elseif ($val instanceof ServiceDefinition) {
					$val = '@' . current(array_keys($this->builder->getDefinitions(), $val, true));

				} elseif (is_string($val) && strlen($val) > 1 && $val[0] === '@' && $val[1] !== '@') {
					$pair = explode('::', $val, 2);
					$name = $this->getServiceName($pair[0]);
					if (!isset($pair[1])) { // @service
						$val = '@' . $name;
					} elseif (preg_match('#^[A-Z][A-Z0-9_]*\z#', $pair[1], $m)) { // @service::CONSTANT
						$val = ContainerBuilder::literal($this->builder->getDefinition($name)->getType() . '::' . $pair[1]);
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
	 * Add missing arguments using autowiring.
	 */
	private function autowireArguments(string $class, string $method, array $arguments): array
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
		return Autowiring::completeArguments($rm, $arguments, $this);
	}


	/**
	 * @return string|array  Class, @service, [Class, member], [@service, member], [, globalFunc], [Statement, member]
	 */
	private function normalizeEntity($entity)
	{
		if (is_string($entity) && Strings::contains($entity, '::') && !Strings::contains($entity, '?')) { // Class::method -> [Class, method]
			$entity = explode('::', $entity);
		}

		if (is_array($entity) && $entity[0] instanceof ServiceDefinition) { // [ServiceDefinition, ...] -> [@serviceName, ...]
			$entity[0] = '@' . current(array_keys($this->builder->getDefinitions(), $entity[0], true));

		} elseif ($entity instanceof ServiceDefinition) { // ServiceDefinition -> @serviceName
			$entity = '@' . current(array_keys($this->builder->getDefinitions(), $entity, true));
		}
		return $entity;
	}


	/**
	 * Converts @service or @\Class to service name (or type if not possible during resolving) and checks its existence.
	 */
	private function getServiceName($arg): ?string
	{
		if (!is_string($arg) || !preg_match('#^@[\w\\\\.][^:]*\z#', $arg)) {
			return null;
		}
		$service = substr($arg, 1);
		if ($service === ContainerBuilder::THIS_SERVICE) {
			$service = $this->currentService;
		}
		if (Strings::contains($service, '\\')) {
			try {
				$res = $this->getByType($service);
			} catch (NotAllowedDuringResolvingException $e) {
				return $service;
			}
			if (!$res) {
				throw new ServiceCreationException("Reference to missing service of type $service.");
			}
			return $res;
		}
		if (!$this->builder->hasDefinition($service)) {
			throw new ServiceCreationException("Reference to missing service '$service'.");
		}
		return $service;
	}


	/**
	 * Resolves service name by type (taking into account local-autowiring).
	 */
	public function getByType(string $type): ?string
	{
		if (
			$this->currentService !== null
			&& is_a($this->builder->getDefinition((string) $this->currentService)->getType(), $type, true)
		) {
			return $this->currentService;
		}
		return $this->builder->getByType($type, false);
	}


	/**
	 * Adds item to the list of dependencies.
	 * @param  \ReflectionClass|\ReflectionFunctionAbstract|string  $dep
	 * @return static
	 */
	public function addDependency($dep)
	{
		$this->builder->addDependency($dep);
		return $this;
	}
}
