<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Definitions;

use Nette;
use Nette\DI\Helpers;
use Nette\DI\ServiceCreationException;
use Nette\PhpGenerator as Php;
use Nette\Utils\Reflection;
use Nette\Utils\Type;


/**
 * Definition of standard service.
 */
final class FactoryDefinition extends Definition
{
	private const METHOD_CREATE = 'create';

	public array $parameters = [];
	private Definition $resultDefinition;


	public function __construct()
	{
		$this->resultDefinition = new ServiceDefinition;
	}


	public function setImplement(string $interface): static
	{
		if (!interface_exists($interface)) {
			throw new Nette\InvalidArgumentException(sprintf(
				"[%s]\nInterface '%s' not found.",
				$this->getDescriptor(),
				$interface,
			));
		}
		$rc = new \ReflectionClass($interface);
		$method = $rc->getMethods()[0] ?? null;
		if (!$method || $method->isStatic() || $method->name !== self::METHOD_CREATE || count($rc->getMethods()) > 1) {
			throw new Nette\InvalidArgumentException(sprintf(
				"[%s]\nInterface %s must have just one non-static method create().",
				$this->getDescriptor(),
				$interface,
			));
		}
		try {
			Helpers::ensureClassType(Type::fromReflection($method), "return type of $interface::create()", $this->getDescriptor());
		} catch (Nette\DI\ServiceCreationException $e) {
			trigger_error($e->getMessage(), E_USER_DEPRECATED);
		}
		return parent::setType($interface);
	}


	public function getImplement(): ?string
	{
		return $this->getType();
	}


	final public function getResultType(): ?string
	{
		return $this->resultDefinition->getType();
	}


	public function setResultDefinition(Definition $definition): static
	{
		$this->resultDefinition = $definition;
		return $this;
	}


	/** @return ServiceDefinition */
	public function getResultDefinition(): Definition
	{
		return $this->resultDefinition;
	}


	/** @deprecated */
	public function setParameters(array $params): static
	{
		if ($params) {
			$old = $new = [];
			foreach ($params as $k => $v) {
				$tmp = explode(' ', is_int($k) ? $v : $k);
				$old[] = '%' . end($tmp) . '%';
				$new[] = '$' . end($tmp);
			}
			trigger_error(sprintf(
				"[%s]\nOption 'parameters' is deprecated and should be removed. The %s should be replaced with %s in configuration.",
				$this->getDescriptor(),
				implode(', ', $old),
				implode(', ', $new),
			), E_USER_DEPRECATED);
		}
		$this->parameters = $params;
		return $this;
	}


	/** @deprecated */
	public function getParameters(): array
	{
		return $this->parameters;
	}


	public function resolveType(Nette\DI\Resolver $resolver): void
	{
		$interface = $this->getType();
		if (!$interface) {
			throw new ServiceCreationException('Type is missing in definition of service.');
		}
		$method = new \ReflectionMethod($interface, self::METHOD_CREATE);
		$type = Type::fromReflection($method) ?? Helpers::getReturnTypeAnnotation($method);

		$resultDef = $this->resultDefinition;
		try {
			$resolver->resolveDefinition($resultDef);
		} catch (ServiceCreationException $e) {
			if ($resultDef->getType()) {
				throw $e;
			}
			$resultDef->setType(Helpers::ensureClassType($type, "return type of $interface::create()"));
			$resolver->resolveDefinition($resultDef);
		}

		if ($type && !$type->allows($resultDef->getType())) {
			throw new ServiceCreationException(sprintf(
				"[%s]\nFactory for %s cannot create incompatible %s type.",
				$this->getDescriptor(),
				$type,
				$resultDef->getType(),
			));
		}
	}


	public function complete(Nette\DI\Resolver $resolver): void
	{
		$resultDef = $this->resultDefinition;

		if ($resultDef instanceof ServiceDefinition) {
			if (!$this->parameters) {
				$this->completeParameters($resolver);
			}
			$this->convertArguments($resultDef->getFactory()->arguments);
			foreach ($resultDef->getSetup() as $setup) {
				$this->convertArguments($setup->arguments);
			}

			if ($resultDef->getEntity() instanceof Reference && !$resultDef->getFactory()->arguments) {
				$resultDef->setFactory([ // render as $container->createMethod()
					new Reference(Nette\DI\ContainerBuilder::THIS_CONTAINER),
					Nette\DI\Container::getMethodName($resultDef->getEntity()->getValue()),
				]);
			}
		}

		$resolver->completeDefinition($resultDef);
	}


	private function completeParameters(Nette\DI\Resolver $resolver): void
	{
		$interface = $this->getType();
		$method = new \ReflectionMethod($interface, self::METHOD_CREATE);

		$ctorParams = [];
		if (
			($class = $resolver->resolveEntityType($this->resultDefinition->getFactory()))
			&& ($ctor = (new \ReflectionClass($class))->getConstructor())
		) {
			foreach ($ctor->getParameters() as $param) {
				$ctorParams[$param->name] = $param;
			}
		}

		foreach ($method->getParameters() as $param) {
			$methodType = Type::fromReflection($param);
			if (isset($ctorParams[$param->name])) {
				$ctorParam = $ctorParams[$param->name];
				$ctorType = Type::fromReflection($ctorParam);
				if ($ctorType && !$ctorType->allows((string) $methodType)) {
					throw new ServiceCreationException(sprintf(
						"Type of \$%s in %s::create() doesn't match type in %s constructor.",
						$param->name,
						$interface,
						$class,
					));
				}
				$this->resultDefinition->getFactory()->arguments[$ctorParam->getPosition()] = new Php\Literal('$' . $ctorParam->name);

			} elseif (!$this->resultDefinition->getSetup()) {
				$hint = Nette\Utils\Helpers::getSuggestion(array_keys($ctorParams), $param->name);
				throw new ServiceCreationException(sprintf(
					'Unused parameter $%s when implementing method %s::create()',
					$param->name,
					$interface,
				) . ($hint ? ", did you mean \${$hint}?" : '.'));
			}

			$paramDef = $methodType . ' ' . $param->name;
			if ($param->isDefaultValueAvailable()) {
				$this->parameters[$paramDef] = Reflection::getParameterDefaultValue($param);
			} else {
				$this->parameters[] = $paramDef;
			}
		}
	}


	public function convertArguments(array &$args): void
	{
		foreach ($args as &$v) {
			if (is_string($v) && $v && $v[0] === '$') {
				$v = new Php\Literal($v);
			}
		}
	}


	public function generateMethod(Php\Method $method, Nette\DI\PhpGenerator $generator): void
	{
		$class = (new Php\ClassType)
			->addImplement($this->getType());

		$class->addProperty('container')
			->setPrivate();

		$class->addMethod('__construct')
			->addBody('$this->container = $container;')
			->addParameter('container')
			->setType($generator->getClassName());

		$methodCreate = $class->addMethod(self::METHOD_CREATE);
		$this->resultDefinition->generateMethod($methodCreate, $generator);
		$body = $methodCreate->getBody();
		$body = str_replace('$this', '$this->container', $body);
		$body = str_replace('$this->container->container', '$this->container', $body);

		$rm = new \ReflectionMethod($this->getType(), self::METHOD_CREATE);
		$methodCreate
			->setParameters($generator->convertParameters($this->parameters))
			->setReturnType((string) (Type::fromReflection($rm) ?? $this->getResultType()))
			->setBody($body);

		$method->setBody('return new class ($this) ' . $class . ';');
	}


	public function __clone()
	{
		parent::__clone();
		$this->resultDefinition = unserialize(serialize($this->resultDefinition));
	}
}
