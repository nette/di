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
use Nette\Utils\Type;


/**
 * Definition of standard service.
 */
final class FactoryDefinition extends Definition
{
	private const MethodCreate = 'create';

	private Definition $resultDefinition;


	public function __construct()
	{
		$this->resultDefinition = new ServiceDefinition;
	}


	public function setImplement(string $interface): static
	{
		if (!interface_exists($interface)) {
			throw new Nette\InvalidArgumentException(sprintf(
				"Service '%s': Interface '%s' not found.",
				$this->getName(),
				$interface,
			));
		}

		$rc = new \ReflectionClass($interface);
		$method = $rc->getMethods()[0] ?? null;
		if (!$method || $method->isStatic() || $method->name !== self::MethodCreate || count($rc->getMethods()) > 1) {
			throw new Nette\InvalidArgumentException(sprintf(
				"Service '%s': Interface %s must have just one non-static method create().",
				$this->getName(),
				$interface,
			));
		}

		Helpers::ensureClassType(Type::fromReflection($method), "return type of $interface::create()");
		return parent::setType($interface);
	}


	public function getImplement(): ?string
	{
		return $this->getType();
	}


	public function getResultType(): ?string
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


	public function resolveType(Nette\DI\Resolver $resolver): void
	{
		if (!$this->getType()) {
			throw new ServiceCreationException('Type is missing in definition of service.');
		}

		$type = Type::fromReflection(new \ReflectionMethod($this->getType(), self::MethodCreate));

		$resultDef = $this->resultDefinition;
		try {
			$resolver->resolveDefinition($resultDef);
		} catch (ServiceCreationException $e) {
			if ($resultDef->getType()) {
				throw $e;
			}

			$resultDef->setType($type->getSingleName());
			$resolver->resolveDefinition($resultDef);
		}

		if (!$type->allows($resultDef->getType())) {
			throw new ServiceCreationException(sprintf(
				'Factory for %s cannot create incompatible %s type.',
				$type,
				$resultDef->getType(),
			));
		}
	}


	public function complete(Nette\DI\Resolver $resolver): void
	{
		$resultDef = $this->resultDefinition;

		if ($resultDef instanceof ServiceDefinition) {
			$this->completeParameters($resolver);
			$this->convertArguments($resultDef->getCreator()->arguments);
			foreach ($resultDef->getSetup() as $setup) {
				$this->convertArguments($setup->arguments);
			}

			if ($resultDef->getEntity() instanceof Reference && !$resultDef->getCreator()->arguments) {
				$resultDef->setCreator([ // render as $container->createMethod()
					new Reference(Nette\DI\ContainerBuilder::ThisContainer),
					Nette\DI\Container::getMethodName($resultDef->getEntity()->getValue()),
				]);
			}
		}

		$resolver->completeDefinition($resultDef);
	}


	private function completeParameters(Nette\DI\Resolver $resolver): void
	{
		$interface = $this->getType();
		$method = new \ReflectionMethod($interface, self::MethodCreate);

		$ctorParams = [];
		if (
			($class = $resolver->resolveEntityType($this->resultDefinition->getCreator()))
			&& ($ctor = (new \ReflectionClass($class))->getConstructor())
		) {
			foreach ($ctor->getParameters() as $param) {
				$ctorParams[$param->name] = $param;
			}
		}

		foreach ($method->getParameters() as $param) {
			if (isset($ctorParams[$param->name])) {
				$ctorParam = $ctorParams[$param->name];
				$ctorType = Type::fromReflection($ctorParam);
				if ($ctorType && !$ctorType->allows((string) Type::fromReflection($param))) {
					throw new ServiceCreationException(sprintf(
						"Type of \$%s in %s::create() doesn't match type in %s constructor.",
						$param->name,
						$interface,
						$class,
					));
				}

				$this->resultDefinition->getCreator()->arguments[$ctorParam->getPosition()] = new Php\Literal('$' . $ctorParam->name);

			} elseif (!$this->resultDefinition->getSetup()) {
				// [param1, param2] => '$param1, $param2'
				$stringifyParams = fn(array $params): string => implode(
					', ',
					array_map(fn(string $param) => '$' . $param, $params),
				);
				$ctorParamsKeys = array_keys($ctorParams);
				$hint = Nette\Utils\Helpers::getSuggestion($ctorParamsKeys, $param->name);
				throw new ServiceCreationException(sprintf(
					'Cannot implement %s::create(): factory method parameters (%s) are not matching %s::__construct() parameters (%s).',
					$interface,
					$stringifyParams(array_map(fn(\ReflectionParameter $param) => $param->name, $method->getParameters())),
					$class,
					$stringifyParams($ctorParamsKeys),
				) . ($hint ? " Did you mean to use '\${$hint}' in factory method?" : ''));
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

		$class->addMethod('__construct')
			->addPromotedParameter('container')
				->setPrivate()
				->setType($generator->getClassName());

		$methodCreate = $class->addMethod(self::MethodCreate);
		$this->resultDefinition->generateMethod($methodCreate, $generator);
		$body = $methodCreate->getBody();
		$body = str_replace('$this', '$this->container', $body);
		$body = str_replace('$this->container->container', '$this->container', $body);

		$rm = new \ReflectionMethod($this->getType(), self::MethodCreate);
		$methodCreate
			->setParameters(array_map((new Php\Factory)->fromParameterReflection(...), $rm->getParameters()))
			->setReturnType((string) Type::fromReflection($rm))
			->setBody($body);

		$method->setBody('return new class ($this) ' . $class . ';');
	}


	public function __clone()
	{
		parent::__clone();
		$this->resultDefinition = unserialize(serialize($this->resultDefinition));
	}
}
