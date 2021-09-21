<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Definitions;

use Nette;
use Nette\DI\ServiceCreationException;
use Nette\Utils\Reflection;
use Nette\Utils\Type;


/**
 * Definition of standard service.
 */
final class FactoryDefinition extends Definition
{
	private const METHOD_CREATE = 'create';

	/** @var array */
	public $parameters = [];

	/** @var Definition */
	private $resultDefinition;


	public function __construct()
	{
		$this->resultDefinition = new ServiceDefinition;
	}


	/** @return static */
	public function setImplement(string $type)
	{
		if (!interface_exists($type)) {
			throw new Nette\InvalidArgumentException(sprintf(
				"Service '%s': Interface '%s' not found.",
				$this->getName(),
				$type
			));
		}
		$rc = new \ReflectionClass($type);
		$method = $rc->getMethods()[0] ?? null;
		if (!$method || $method->isStatic() || $method->name !== self::METHOD_CREATE || count($rc->getMethods()) > 1) {
			throw new Nette\InvalidArgumentException(sprintf(
				"Service '%s': Interface %s must have just one non-static method create().",
				$this->getName(),
				$type
			));
		}
		return parent::setType($type);
	}


	public function getImplement(): ?string
	{
		return $this->getType();
	}


	final public function getResultType(): ?string
	{
		return $this->resultDefinition->getType();
	}


	/** @return static */
	public function setResultDefinition(Definition $definition)
	{
		$this->resultDefinition = $definition;
		return $this;
	}


	/** @return ServiceDefinition */
	public function getResultDefinition(): Definition
	{
		return $this->resultDefinition;
	}


	/**
	 * @deprecated use ->getResultDefinition()->setFactory()
	 * @return static
	 */
	public function setFactory($factory, array $args = [])
	{
		trigger_error(sprintf('Service %s: %s() is deprecated, use ->getResultDefinition()->setFactory()', $this->getName(), __METHOD__), E_USER_DEPRECATED);
		$this->resultDefinition->setFactory($factory, $args);
		return $this;
	}


	/** @deprecated use ->getResultDefinition()->getFactory() */
	public function getFactory(): ?Statement
	{
		trigger_error(sprintf('Service %s: %s() is deprecated, use ->getResultDefinition()->getFactory()', $this->getName(), __METHOD__), E_USER_DEPRECATED);
		return $this->resultDefinition->getFactory();
	}


	/**
	 * @deprecated use ->getResultDefinition()->getEntity()
	 * @return mixed
	 */
	public function getEntity()
	{
		trigger_error(sprintf('Service %s: %s() is deprecated, use ->getResultDefinition()->getEntity()', $this->getName(), __METHOD__), E_USER_DEPRECATED);
		return $this->resultDefinition->getEntity();
	}


	/**
	 * @deprecated use ->getResultDefinition()->setArguments()
	 * @return static
	 */
	public function setArguments(array $args = [])
	{
		trigger_error(sprintf('Service %s: %s() is deprecated, use ->getResultDefinition()->setArguments()', $this->getName(), __METHOD__), E_USER_DEPRECATED);
		$this->resultDefinition->setArguments($args);
		return $this;
	}


	/**
	 * @deprecated use ->getResultDefinition()->setSetup()
	 * @return static
	 */
	public function setSetup(array $setup)
	{
		trigger_error(sprintf('Service %s: %s() is deprecated, use ->getResultDefinition()->setSetup()', $this->getName(), __METHOD__), E_USER_DEPRECATED);
		$this->resultDefinition->setSetup($setup);
		return $this;
	}


	/** @deprecated use ->getResultDefinition()->getSetup() */
	public function getSetup(): array
	{
		trigger_error(sprintf('Service %s: %s() is deprecated, use ->getResultDefinition()->getSetup()', $this->getName(), __METHOD__), E_USER_DEPRECATED);
		return $this->resultDefinition->getSetup();
	}


	/**
	 * @deprecated use ->getResultDefinition()->addSetup()
	 * @return static
	 */
	public function addSetup($entity, array $args = [])
	{
		trigger_error(sprintf('Service %s: %s() is deprecated, use ->getResultDefinition()->addSetup()', $this->getName(), __METHOD__), E_USER_DEPRECATED);
		$this->resultDefinition->addSetup($entity, $args);
		return $this;
	}


	/** @return static */
	public function setParameters(array $params)
	{
		$this->parameters = $params;
		return $this;
	}


	public function getParameters(): array
	{
		return $this->parameters;
	}


	public function resolveType(Nette\DI\Resolver $resolver): void
	{
		$resultDef = $this->resultDefinition;
		try {
			$resolver->resolveDefinition($resultDef);
			return;
		} catch (ServiceCreationException $e) {
		}

		if (!$resultDef->getType()) {
			$interface = $this->getType();
			if (!$interface) {
				throw new ServiceCreationException('Type is missing in definition of service.');
			}
			$method = new \ReflectionMethod($interface, self::METHOD_CREATE);
			$returnType = Nette\DI\Helpers::getReturnType($method);
			if (!$returnType) {
				throw new ServiceCreationException(sprintf('Method %s::create() has no return type or annotation @return.', $interface));
			} elseif (!class_exists($returnType) && !interface_exists($returnType)) {
				throw new ServiceCreationException(sprintf(
					"Class '%s' not found.\nCheck the return type or annotation @return of the %s::create() method.",
					$returnType,
					$interface
				));
			}
			$resultDef->setType($returnType);
		}

		$resolver->resolveDefinition($resultDef);
	}


	public function complete(Nette\DI\Resolver $resolver): void
	{
		$resultDef = $this->resultDefinition;

		if ($resultDef instanceof ServiceDefinition) {
			if (!$this->parameters) {
				$this->completeParameters($resolver);
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
						$class
					));
				}
				$this->resultDefinition->getFactory()->arguments[$ctorParam->getPosition()] = Nette\DI\ContainerBuilder::literal('$' . $ctorParam->name);

			} elseif (!$this->resultDefinition->getSetup()) {
				$hint = Nette\Utils\Helpers::getSuggestion(array_keys($ctorParams), $param->name);
				throw new ServiceCreationException(sprintf(
					'Unused parameter $%s when implementing method %s::create()',
					$param->name,
					$interface
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


	public function generateMethod(Nette\PhpGenerator\Method $method, Nette\DI\PhpGenerator $generator): void
	{
		$class = (new Nette\PhpGenerator\ClassType)
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
