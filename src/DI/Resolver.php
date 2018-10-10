<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI;

use Nette;
use Nette\DI\Definitions\Reference;
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

	/** @var string[] */
	private $recursive = [];


	public function __construct(ContainerBuilder $builder)
	{
		$this->builder = $builder;
	}


	public function resolveDefinition(ServiceDefinition $def): void
	{
		$name = $def->getName();
		if (isset($this->recursive[$name])) {
			throw new ServiceCreationException(sprintf('Circular reference detected for services: %s.', implode(', ', array_keys($this->recursive))));
		}

		try {
			$this->recursive[$name] = true;

			// prepare generated factories
			if ($def->getImplement()) {
				$this->resolveImplement($def);
			}

			if ($def->isDynamic()) {
				if (!$def->getType()) {
					throw new ServiceCreationException('Type is missing in definition of service.');
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
				&& ($alias = $this->normalizeReference($def->getFactory()->getEntity()))
				&& (!$def->getImplement() || ($alias->isName() && $definitions[$alias->getValue()]->getImplement()))
			) {
				$def->setAutowired(false);
			}

			// resolve type
			$factoryClass = $def->getFactory() ? $this->resolveEntityType($def->getFactory()->getEntity()) : null; // call always to check entities
			if ($type = $def->getType() ?: $factoryClass) {
				if (!class_exists($type) && !interface_exists($type)) {
					throw new ServiceCreationException("Class or interface '$type' used in service '$name' not found.");
				}
				$type = Helpers::normalizeClass($type);
				$def->setType($type);
				if (count($this->recursive) === 1) {
					$this->addDependency(new ReflectionClass($factoryClass ?: $type));
				}

			} elseif ($def->getAutowired()) {
				throw new ServiceCreationException("Unknown type of service '$name', declare return type of factory method (for PHP 5 use annotation @return)");
			}

		} finally {
			unset($this->recursive[$name]);
		}
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
				$def->setFactory(Reference::fromType($def->getType()));
			} elseif (!$this->normalizeReference($def->getFactory()->getEntity())) {
				throw new ServiceCreationException("Invalid factory in service '$name' definition.");
			}
		}

		if (!$def->parameters) {
			$ctorParams = [];
			if (!$def->getEntity()) {
				$def->setFactory($def->getType(), $def->getFactory() ? $def->getFactory()->arguments : []);
			}
			if (
				($class = $this->resolveEntityType($def->getFactory()))
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


	private function resolveReferenceType(Reference $ref): ?string
	{
		if (!$ref->isName()) {
			return $ref->getValue();
		}
		$def = $this->builder->getDefinition($ref->getValue());
		if (!$def->getType()) {
			$this->resolveDefinition($def);
		}
		return $def->getType();
	}


	private function resolveEntityType($entity): ?string
	{
		$definitions = $this->builder->getDefinitions();
		$entity = $this->normalizeEntity($entity instanceof Statement ? $entity->getEntity() : $entity);
		$serviceName = current(array_slice(array_keys($this->recursive), -1));

		if (is_array($entity)) {
			if ($entity[0] instanceof Reference || $entity[0] instanceof Statement) {
				$entity[0] = $this->resolveEntityType($entity[0]);
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

		} elseif ($entity instanceof Reference) { // alias or factory
			if ($entity->isType()) { // @\Class
				return $entity->getValue();
			}
			$service = $entity->getValue();
			return $definitions[$service]->getImplement() ?: $this->resolveReferenceType($entity);

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
		$serviceRef = $this->normalizeReference($entity);
		$factory = $serviceRef && $serviceRef->isName() && !$def->getFactory()->arguments && !$def->getSetup() && $def->getImplementMode() !== $def::IMPLEMENT_MODE_CREATE
			? new Statement([new Reference(ContainerBuilder::THIS_CONTAINER), 'getService'], [$serviceRef->getValue()])
			: $def->getFactory();

		try {
			$def->setFactory($this->completeStatement($factory));

			$this->currentService = $def->getName();
			$setups = $def->getSetup();
			foreach ($setups as &$setup) {
				if (is_string($setup->getEntity()) && strpbrk($setup->getEntity(), ':@?\\') === false) { // auto-prepend @self
					$setup = new Statement([new Reference(Reference::SELF), $setup->getEntity()], $setup->arguments);
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
		$arguments = $this->convertReferences($statement->arguments);
		$definitions = $this->builder->getDefinitions();

		if (is_string($entity) && Strings::contains($entity, '?')) { // PHP literal

		} elseif ($entity instanceof Reference) { // factory calling
			$params = [];
			foreach ($definitions[$entity->getValue()]->parameters as $k => $v) {
				$params[] = preg_replace('#\w+\z#', '\$$0', (is_int($k) ? $v : $k)) . (is_int($k) ? '' : ' = ' . PhpHelpers::dump($v));
			}
			$rm = new \ReflectionFunction(eval('return function(' . implode(', ', $params) . ') {};'));
			$arguments = $this->autowireArguments($rm, $arguments);

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
				$arguments = $this->autowireArguments($constructor, $arguments);
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
			$arguments = $this->autowireArguments($rf, $arguments);

		} else {
			if ($entity[0] instanceof Statement) {
				$entity[0] = $this->completeStatement($entity[0]);
			}

			if ($entity[1][0] === '$') { // property getter, setter or appender
				Validators::assert($arguments, 'list:0..1', "setup arguments for '" . Nette\Utils\Callback::toString($entity) . "'");
				if (!$arguments && substr($entity[1], -2) === '[]') {
					throw new ServiceCreationException("Missing argument for $entity[1].");
				}
			} elseif (
				$type = !$entity[0] instanceof Reference || $entity[1] === 'create'
					? $this->resolveEntityType($entity[0])
					: $definitions[$entity[0]->getValue()]->getType()
			) {
				$rc = new ReflectionClass($type);
				if ($rc->hasMethod($entity[1])) {
					$rm = $rc->getMethod($entity[1]);
					if (!$rm->isPublic()) {
						throw new ServiceCreationException("$type::$entity[1]() is not callable.");
					}
					$arguments = $this->autowireArguments($rm, $arguments);

				} elseif (!Nette\Utils\Arrays::isList($arguments)) {
					throw new ServiceCreationException("Unable to pass specified arguments to $type::$entity[1]().");
				}
			}
		}

		try {
			array_walk_recursive($arguments, function (&$val): void {
				if ($val instanceof Statement) {
					$val = $this->completeStatement($val);

				} elseif ($val instanceof ServiceDefinition) {
					$val = $this->normalizeEntity($val);

				} elseif ($val instanceof Reference) {
					$val = $this->normalizeReference($val);
				}
			});

		} catch (ServiceCreationException $e) {
			$toText = function ($x) { return $x instanceof Reference ? '@' . $x->getValue() : $x; } ;
			if ((is_string($entity) || $entity instanceof Reference || is_array($entity)) && !strpos($e->getMessage(), ' (used in')) {
				$desc = (is_string($entity) || $entity instanceof Reference)
					? $toText($entity) . '::__construct'
					: ((is_string($entity[0]) || $entity[0] instanceof Reference) ? ($toText($entity[0]) . '::') : 'method ') . $entity[1];
				$e->setMessage($e->getMessage() . " (used in $desc)");
			}
			throw $e;
		}

		return new Statement($entity, $arguments);
	}


	/**
	 * Add missing arguments using autowiring.
	 */
	private function autowireArguments(\ReflectionFunctionAbstract $function, array $arguments): array
	{
		if (!$function->isClosure()) {
			$this->addDependency($function);
		}
		return Autowiring::completeArguments($function, $arguments, $this);
	}


	/**
	 * @return string|array|Reference  literal, Class, Reference, [Class, member], [, globalFunc], [Reference, member], [Statement, member]
	 */
	private function normalizeEntity($entity)
	{
		if (is_string($entity) && Strings::contains($entity, '::') && !Strings::contains($entity, '?')) { // Class::method -> [Class, method]
			$entity = explode('::', $entity);
		}

		if (is_array($entity)) {
			$item = &$entity[0];
		} else {
			$item = &$entity;
		}

		if ($item instanceof ServiceDefinition) {
			$item = new Reference(current(array_keys($this->builder->getDefinitions(), $item, true)));

		} elseif ($ref = $this->normalizeReference($item)) { // @service|Reference -> resolved Reference
			$item = $ref;
		}

		return $entity;
	}


	/**
	 * Converts @service or @\Class to service name (or type if not possible during resolving) and checks its existence.
	 */
	private function normalizeReference($arg): ?Reference
	{
		if ($arg instanceof Reference) {
			$service = $arg->getValue();
		} elseif (is_string($arg) && preg_match('#^@[\w\\\\.][^:]*\z#', $arg)) {
			$service = substr($arg, 1);
		} else {
			return null;
		}
		if ($service === Reference::SELF) {
			$service = $this->currentService;
		}
		if (Strings::contains($service, '\\')) {
			try {
				$res = $this->getByType($service);
			} catch (NotAllowedDuringResolvingException $e) {
				return new Reference($service);
			}
			if (!$res) {
				throw new ServiceCreationException("Reference to missing service of type $service.");
			}
			return $res;
		}
		if (!$this->builder->hasDefinition($service)) {
			throw new ServiceCreationException("Reference to missing service '$service'.");
		}
		return new Reference($service);
	}


	/**
	 * Returns named reference to service resolved by type (taking into account local-autowiring).
	 */
	public function getByType(string $type): Reference
	{
		if (
			$this->currentService !== null
			&& is_a($this->builder->getDefinition((string) $this->currentService)->getType(), $type, true)
		) {
			return new Reference($this->currentService);
		}
		return new Reference($this->builder->getByType($type, true));
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


	private function convertReferences(array $arguments): array
	{
		array_walk_recursive($arguments, function (&$val): void {
			if (is_string($val) && strlen($val) > 1 && $val[0] === '@' && $val[1] !== '@') {
				$pair = explode('::', substr($val, 1), 2);
				if (!isset($pair[1])) { // @service
					$val = new Reference($pair[0]);
				} elseif (preg_match('#^[A-Z][A-Z0-9_]*\z#', $pair[1], $m)) { // @service::CONSTANT
					$ref = $this->normalizeReference(new Reference($pair[0]));
					$val = ContainerBuilder::literal($this->resolveReferenceType($ref) . '::' . $pair[1]);
				} else { // @service::property
					$val = new Statement([new Reference($pair[0]), '$' . $pair[1]]);
				}

			} elseif (is_string($val) && substr($val, 0, 2) === '@@') { // escaped text @@
				$val = substr($val, 1);
			}
		});
		return $arguments;
	}
}
