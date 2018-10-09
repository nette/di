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


/**
 * Definition of standard service.
 */
final class FactoryDefinition extends Definition
{
	private const METHOD_CREATE = 'create';

	/** @var array */
	public $parameters = [];

	/** @var Definition */
	private $createdDefinition;


	public function __construct()
	{
		$this->createdDefinition = new ServiceDefinition;
	}


	/**
	 * @return static
	 */
	public function setImplement(?string $type)
	{
		if ($type !== null && !interface_exists($type)) {
			throw new Nette\InvalidArgumentException("Service '{$this->getName()}': Interface '$type' not found.");
		}
		$rc = new \ReflectionClass($type);
		$method = $rc->hasMethod(self::METHOD_CREATE) ? $rc->getMethod(self::METHOD_CREATE) : null;
		if (count($rc->getMethods()) !== 1 || !$method || $method->isStatic()) {
			throw new Nette\InvalidArgumentException("Interface $type must have just one non-static method create() or get().");
		}
		return parent::setType($type);
	}


	public function getImplement(): ?string
	{
		return $this->getType();
	}


	/**
	 * @return static
	 */
	public function setReturnedType(?string $type)
	{
		$this->createdDefinition->setType($type);
		return $this;
	}


	/**
	 * @return ServiceDefinition
	 */
	final public function getReturnedType(): ?string
	{
		return $this->createdDefinition->getType();
	}


	/**
	 * @return static
	 */
	public function setCreatedDefinition(Definition $definition)
	{
		$this->createdDefinition = $definition;
		return $this;
	}


	public function getCreatedDefinition(): Definition
	{
		return $this->createdDefinition;
	}


	/**
	 * @deprecated Use getCreatedDefinition()->setFactory()
	 */
	public function setFactory($factory, array $args = [])
	{
		$this->createdDefinition->setFactory($factory, $args);
		return $this;
	}


	/**
	 * @deprecated Use getCreatedDefinition()->getFactory()
	 */
	public function getFactory(): ?Statement
	{
		return $this->createdDefinition->getFactory();
	}


	/**
	 * @deprecated Use getCreatedDefinition()->getEntity()
	 */
	public function getEntity()
	{
		return $this->createdDefinition->getEntity();
	}


	/**
	 * @deprecated Use getCreatedDefinition()->setArguments()
	 */
	public function setArguments(array $args = [])
	{
		$this->createdDefinition->setArguments($args);
		return $this;
	}


	/**
	 * @deprecated Use getCreatedDefinition()->setSetup()
	 */
	public function setSetup(array $setup)
	{
		$this->createdDefinition->setSetup($setup);
		return $this;
	}


	/**
	 * @deprecated Use getCreatedDefinition()->getSetup()
	 */
	public function getSetup(): array
	{
		return $this->createdDefinition->getSetup();
	}


	/**
	 * @deprecated Use getCreatedDefinition()->addSetup()
	 */
	public function addSetup($entity, array $args = [])
	{
		$this->createdDefinition->addSetup($entity, $args);
		return $this;
	}


	/**
	 * @return static
	 */
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
		$interface = $this->getType();
		$method = new \ReflectionMethod($interface, self::METHOD_CREATE);
		$createdDef = $this->createdDefinition;

		if (!$createdDef->getType() && !$createdDef->getEntity()) {
			$returnType = Nette\DI\Helpers::getReturnType($method);
			if (!$returnType) {
				throw new ServiceCreationException("Method $interface::create() has not return type hint or annotation @return.");
			} elseif (!class_exists($returnType)) {
				throw new ServiceCreationException("Check a type hint or annotation @return of the $interface::create() method, class '$returnType' cannot be found.");
			}
			$createdDef->setType($returnType);
		}

		if (!$createdDef->getEntity()) {
			$createdDef->setFactory($createdDef->getType(), $createdDef->getFactory() ? $createdDef->getFactory()->arguments : []);
		}

		$createdDef->resolveType($resolver);
	}


	public function complete(Nette\DI\Resolver $resolver): void
	{
		if (!$this->parameters) {
			$this->completeParameters($resolver);
		}

		$this->createdDefinition
			->setName($this->getName())
			->setImplementMode('create')
			->complete($resolver);
	}


	private function completeParameters(Nette\DI\Resolver $resolver): void
	{
		$interface = $this->getType();
		$method = new \ReflectionMethod($interface, self::METHOD_CREATE);

		$ctorParams = [];
		if (
			($class = $resolver->resolveEntityType($this->createdDefinition->getFactory()))
			&& ($ctor = (new \ReflectionClass($class))->getConstructor())
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
					throw new ServiceCreationException("Type hint for \${$param->getName()} in $interface::create() doesn't match type hint in $class constructor."
					);
				}
				$this->createdDefinition->getFactory()->arguments[$arg->getPosition(
				)] = Nette\DI\ContainerBuilder::literal('$' . $arg->getName());

			} elseif (!$this->createdDefinition->getSetup()) {
				$hint = Nette\Utils\ObjectHelpers::getSuggestion(array_keys($ctorParams), $param->getName());
				throw new ServiceCreationException("Unused parameter \${$param->getName()} when implementing method $interface::create()" . ($hint ? ", did you mean \${$hint}?" : '.')
				);
			}
			$nullable = $hint && $param->allowsNull() && (!$param->isDefaultValueAvailable() || $param->getDefaultValue(
					) !== null);
			$paramDef = ($nullable ? '?' : '') . $hint . ' ' . $param->getName();
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
			->setVisibility('private');

		$class->addMethod('__construct')
			->addBody('$this->container = $container;')
			->addParameter('container')
			->setTypeHint($generator->getClassName());

		$rm = new \ReflectionMethod($this->getType(), self::METHOD_CREATE);

		$methodCreate = $class->addMethod(self::METHOD_CREATE);
		$this->createdDefinition->generateMethod($methodCreate, $generator);
		$methodCreate
			->setParameters($generator->convertParameters($this->parameters))
			->setReturnType(Reflection::getReturnType($rm))
			->setBody(str_replace('$this', '$this->container', $methodCreate->getBody()));

		$method->setBody('return new class ($this) ' . $class . ';');
	}


	public function __clone()
	{
		parent::__clone();
		$this->createdDefinition = unserialize(serialize($this->createdDefinition));
	}
}
