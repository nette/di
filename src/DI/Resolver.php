<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI;

use Nette;
use Nette\DI\Definitions\Definition;
use Nette\DI\Definitions\Reference;
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

	/** @var Definition|null */
	private $currentService;

	/** @var string|null */
	private $currentServiceType;

	/** @var bool */
	private $currentServiceAllowed;

	/** @var \SplObjectStorage  circular reference detector */
	private $recursive;


	public function __construct(ContainerBuilder $builder)
	{
		$this->builder = $builder;
		$this->recursive = new \SplObjectStorage;
	}


	public function getContainerBuilder(): ContainerBuilder
	{
		return $this->builder;
	}


	public function resolveDefinition(Definition $def): void
	{
		if ($this->recursive->contains($def)) {
			$names = array_map(function ($item) { return $item->getName(); }, iterator_to_array($this->recursive));
			throw new ServiceCreationException(sprintf('Circular reference detected for services: %s.', implode(', ', $names)));
		}

		try {
			$this->recursive->attach($def);

			$def->resolveType($this);

			if (!$def->getType()) {
				throw new ServiceCreationException('Type of service is unknown.');
			}
		} catch (\Exception $e) {
			throw $this->completeException($e, $def);

		} finally {
			$this->recursive->detach($def);
		}
	}


	public function resolveReferenceType(Reference $ref): ?string
	{
		if ($ref->isSelf()) {
			return $this->currentServiceType;
		} elseif ($ref->isType()) {
			return ltrim($ref->getValue(), '\\');
		}
		$def = $this->resolveReference($ref);
		if (!$def->getType()) {
			$this->resolveDefinition($def);
		}
		return $def->getType();
	}


	public function resolveEntityType(Statement $statement): ?string
	{
		$entity = $this->normalizeEntity($statement);

		if (is_array($entity)) {
			if ($entity[0] instanceof Reference || $entity[0] instanceof Statement) {
				$entity[0] = $this->resolveEntityType($entity[0] instanceof Statement ? $entity[0] : new Statement($entity[0]));
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
				throw new ServiceCreationException(sprintf('Method %s() is not callable.', Nette\Utils\Callback::toString($entity)), 0, $e ?? null);
			}
			$this->addDependency($reflection);

			$type = Helpers::getReturnType($reflection);
			if ($type && !class_exists($type) && !interface_exists($type)) {
				throw new ServiceCreationException(sprintf("Class or interface '%s' not found. Is return type of %s() correct?", $type, Nette\Utils\Callback::toString($entity)));
			}
			return $type;

		} elseif ($entity instanceof Reference) { // alias or factory
			return $this->resolveReferenceType($entity);

		} elseif (is_string($entity)) { // class
			if (!class_exists($entity)) {
				throw new ServiceCreationException("Class $entity not found.");
			}
			return $entity;
		}
		return null;
	}


	public function completeDefinition(Definition $def): void
	{
		$this->currentService = in_array($def, $this->builder->getDefinitions(), true) ? $def : null;
		$this->currentServiceType = $def->getType();
		$this->currentServiceAllowed = false;

		try {
			$def->complete($this);

			$this->addDependency(new \ReflectionClass($def->getType()));

		} catch (\Exception $e) {
			throw $this->completeException($e, $def);

		} finally {
			$this->currentService = $this->currentServiceType = null;
		}
	}


	public function completeStatement(Statement $statement, bool $currentServiceAllowed = false): Statement
	{
		$this->currentServiceAllowed = $currentServiceAllowed;
		$entity = $this->normalizeEntity($statement);
		$arguments = $this->convertReferences($statement->arguments);

		switch (true) {
			case is_string($entity) && Strings::contains($entity, '?'): // PHP literal
				break;

			case $entity === 'not':
				$entity = ['', '!'];
				break;

			case is_string($entity): // create class
				if (!class_exists($entity)) {
					throw new ServiceCreationException("Class $entity not found.");
				} elseif ((new ReflectionClass($entity))->isAbstract()) {
					throw new ServiceCreationException("Class $entity is abstract.");
				} elseif (($rm = (new ReflectionClass($entity))->getConstructor()) !== null && !$rm->isPublic()) {
					$visibility = $rm->isProtected() ? 'protected' : 'private';
					throw new ServiceCreationException("Class $entity has $visibility constructor.");
				} elseif ($constructor = (new ReflectionClass($entity))->getConstructor()) {
					$arguments = self::autowireArguments($constructor, $arguments, $this, $this->currentService);
					$this->addDependency($constructor);
				} elseif ($arguments) {
					throw new ServiceCreationException("Unable to pass arguments, class $entity has no constructor.");
				}
				break;

			case $entity instanceof Reference:
				$entity = [new Reference(ContainerBuilder::THIS_CONTAINER), Container::getMethodName($entity->getValue())];
				break;

			case is_array($entity):
				if (!preg_match('#^\$?(\\\\?' . PhpHelpers::PHP_IDENT . ')+(\[\])?\z#', $entity[1])) {
					throw new ServiceCreationException("Expected function, method or property name, '$entity[1]' given.");
				}

				switch (true) {
					case $entity[0] === '': // function call
						if (!Nette\Utils\Arrays::isList($arguments)) {
							throw new ServiceCreationException("Unable to pass specified arguments to $entity[0].");
						} elseif (!function_exists($entity[1])) {
							throw new ServiceCreationException("Function $entity[1] doesn't exist.");
						}
						$rf = new \ReflectionFunction($entity[1]);
						$arguments = self::autowireArguments($rf, $arguments, $this, $this->currentService);
						$this->addDependency($rf);
						break;

					case $entity[0] instanceof Statement:
						$entity[0] = $this->completeStatement($entity[0], $this->currentServiceAllowed);
						// break omitted

					case is_string($entity[0]): // static method call
					case $entity[0] instanceof Reference:
						if ($entity[1][0] === '$') { // property getter, setter or appender
							Validators::assert($arguments, 'list:0..1', "setup arguments for '" . Nette\Utils\Callback::toString($entity) . "'");
							if (!$arguments && substr($entity[1], -2) === '[]') {
								throw new ServiceCreationException("Missing argument for $entity[1].");
							}
						} elseif (
							$type = $entity[0] instanceof Reference
								? $this->resolveReferenceType($entity[0])
								: $this->resolveEntityType($entity[0] instanceof Statement ? $entity[0] : new Statement($entity[0]))
						) {
							$rc = new ReflectionClass($type);
							if ($rc->hasMethod($entity[1])) {
								$rm = $rc->getMethod($entity[1]);
								if (!$rm->isPublic()) {
									throw new ServiceCreationException("$type::$entity[1]() is not callable.");
								}
								$arguments = self::autowireArguments($rm, $arguments, $this, $this->currentService);
								$this->addDependency($rm);

							} elseif (!Nette\Utils\Arrays::isList($arguments)) {
								throw new ServiceCreationException("Unable to pass specified arguments to $type::$entity[1]().");
							}
						}
				}
		}

		try {
			$arguments = $this->completeArguments($arguments);
		} catch (ServiceCreationException $e) {
			if (!strpos($e->getMessage(), ' (used in')) {
				$e->setMessage($e->getMessage() . " (used in {$this->entityToString($entity)})");
			}
			throw $e;
		}

		return new Statement($entity, $arguments);
	}


	private function completeArguments(array $arguments): array
	{
		array_walk_recursive($arguments, function (&$val): void {
			if ($val instanceof Statement) {
				$entity = $val->getEntity();
				if ($entity === 'typed' || $entity === 'tagged') {
					$services = [];
					$current = $this->currentService ? $this->currentService->getName() : null;
					foreach ($val->arguments as $argument) {
						foreach ($entity === 'tagged' ? $this->builder->findByTag($argument) : $this->builder->findAutowired($argument) as $name => $foo) {
							if ($name !== $current) {
								$services[] = new Reference($name);
							}
						}
					}
					$val = $this->completeArguments($services);
				} else {
					$val = $this->completeStatement($val, $this->currentServiceAllowed);
				}

			} elseif ($val instanceof Definition || $val instanceof Reference) {
				$val = $this->normalizeEntity(new Statement($val));
			}
		});
		return $arguments;
	}


	/**
	 * @return string|array|Reference  literal, Class, Reference, [Class, member], [, globalFunc], [Reference, member], [Statement, member]
	 */
	private function normalizeEntity(Statement $statement)
	{
		$entity = $statement->getEntity();
		if (is_array($entity)) {
			$item = &$entity[0];
		} else {
			$item = &$entity;
		}

		if ($item instanceof Definition) {
			$name = current(array_keys($this->builder->getDefinitions(), $item, true));
			if ($name == false) {
				throw new ServiceCreationException("Service '{$item->getName()}' not found in definitions.");
			}
			$item = new Reference($name);
		}
		if ($item instanceof Reference) {
			$item = $this->normalizeReference($item);
		}

		return $entity;
	}


	/**
	 * Normalizes reference to 'self' or named reference (or leaves it typed if it is not possible during resolving) and checks existence of service.
	 */
	public function normalizeReference(Reference $ref): Reference
	{
		$service = $ref->getValue();
		if ($ref->isSelf()) {
			return $ref;
		} elseif ($ref->isName()) {
			if (!$this->builder->hasDefinition($service)) {
				throw new ServiceCreationException("Reference to missing service '$service'.");
			}
			return $this->currentService && $service === $this->currentService->getName()
				? new Reference(Reference::SELF)
				: $ref;
		} else {
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
	}


	public function resolveReference(Reference $ref): Definition
	{
		return $ref->isSelf()
			? $this->currentService
			: $this->builder->getDefinition($ref->getValue());
	}


	/**
	 * Returns named reference to service resolved by type (or 'self' reference for local-autowiring).
	 * @throws ServiceCreationException when multiple found
	 * @throws MissingServiceException when not found
	 */
	public function getByType(string $type): Reference
	{
		if (
			$this->currentService
			&& $this->currentServiceAllowed
			&& is_a($this->currentServiceType, $type, true)
		) {
			return new Reference(Reference::SELF);
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


	private function completeException(\Exception $e, Definition $def): ServiceCreationException
	{
		if ($e instanceof ServiceCreationException && Strings::startsWith($e->getMessage(), "Service '")) {
			return $e;
		} else {
			$name = $def->getName();
			$type = $def->getType();
			if (!$type) {
				$message = "Service '$name': " . $e->getMessage();
			} elseif (!$name || ctype_digit($name)) {
				$message = "Service of type $type: " . str_replace("$type::", '', $e->getMessage());
			} else {
				$message = "Service '$name' (type of $type): " . str_replace("$type::", '', $e->getMessage());
			}
			return $e instanceof ServiceCreationException
				? $e->setMessage($message)
				: new ServiceCreationException($message, 0, $e);
		}
	}


	private function entityToString($entity): string
	{
		$referenceToText = function (Reference $ref): string {
			return $ref->isSelf() && $this->currentService
				? '@' . $this->currentService->getName()
				: '@' . $ref->getValue();
		};
		if (is_string($entity)) {
			return $entity . '::__construct()';
		} elseif ($entity instanceof Reference) {
			$entity = $referenceToText($entity);
		} elseif (is_array($entity)) {
			if (strpos($entity[1], '$') === false) {
				$entity[1] .= '()';
			}
			if ($entity[0] instanceof Reference) {
				$entity[0] = $referenceToText($entity[0]);
			} elseif (!is_string($entity[0])) {
				return $entity[1];
			}
			return implode('::', $entity);
		}
		return (string) $entity;
	}


	private function convertReferences(array $arguments): array
	{
		array_walk_recursive($arguments, function (&$val): void {
			if (is_string($val) && strlen($val) > 1 && $val[0] === '@' && $val[1] !== '@') {
				$pair = explode('::', substr($val, 1), 2);
				if (!isset($pair[1])) { // @service
					$val = new Reference($pair[0]);
				} elseif (preg_match('#^[A-Z][A-Z0-9_]*\z#', $pair[1], $m)) { // @service::CONSTANT
					$val = ContainerBuilder::literal($this->resolveReferenceType(new Reference($pair[0])) . '::' . $pair[1]);
				} else { // @service::property
					$val = new Statement([new Reference($pair[0]), '$' . $pair[1]]);
				}

			} elseif (is_string($val) && substr($val, 0, 2) === '@@') { // escaped text @@
				$val = substr($val, 1);
			}
		});
		return $arguments;
	}


	/**
	 * Add missing arguments using autowiring.
	 * @param  Resolver|Container  $resolver
	 * @throws ServiceCreationException
	 */
	public static function autowireArguments(\ReflectionFunctionAbstract $method, array $arguments, $resolver, Definitions\Definition $current = null): array
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
					$res[$num] = $resolver->getByType($type);
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
				$list = $resolver instanceof self
					? $resolver->getContainerBuilder()->findAutowired($type)
					: array_map([$resolver, 'getService'], $resolver->findAutowired($type));
				$res[$num] = [];
				foreach ($list as $item) {
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
