<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Definitions;

use Nette;
use Nette\DI\Definition;
use Nette\DI\Expressions\Reference;
use Nette\DI\Helpers;
use Nette\DI\ServiceCreationException;
use Nette\PhpGenerator as Php;
use Nette\Utils\Type;
use function count, is_string, sprintf;


/**
 * Definition of a factory service backed by a generated implementation of a user-defined interface.
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
				$this->getName(throw: false) ?? '',
				$interface,
			));
		}

		$rc = new \ReflectionClass($interface);
		$method = $rc->getMethods()[0] ?? null;
		if (!$method || $method->isStatic() || $method->name !== self::MethodCreate || count($rc->getMethods()) > 1) {
			throw new Nette\InvalidArgumentException(sprintf(
				"Service '%s': Interface %s must have just one non-static method create().",
				$this->getName(throw: false) ?? '',
				$interface,
			));
		}

		Helpers::ensureClassType(Type::fromReflection($method), "return type of $interface::create()");
		return parent::setType($interface);
	}


	/** @deprecated Use getType() */
	public function getImplement(): ?string
	{
		return $this->getType();
	}


	public function getResultType(): ?string
	{
		return $this->resultDefinition->getType();
	}


	/**
	 * Configures the creator of the service the factory produces (mirrors NEON, where create:
	 * on a factory definition configures the result, while tags:/autowired: belong to the factory).
	 * @param  string|array{string|Nette\DI\Expression, string}|Definition|Nette\DI\Expression  $creator
	 * @param  array<mixed>  $args
	 */
	public function setCreator(string|array|Definition|Nette\DI\Expression $creator, array $args = []): static
	{
		$this->getServiceResultDefinition()->setCreator($creator, $args);
		return $this;
	}


	/**
	 * Adds a setup step of the service the factory produces (mirrors NEON).
	 * @param  string|array{string, string}|Statement  $target
	 * @param  array<mixed>  $args
	 */
	public function setup(string|array|Statement $target, array $args = []): static
	{
		$this->getServiceResultDefinition()->setup($target, $args);
		return $this;
	}


	/**
	 * Sets the creator arguments of the service the factory produces (mirrors NEON).
	 * @param  array<mixed>  $args
	 */
	public function setArguments(array $args = []): static
	{
		$this->getServiceResultDefinition()->setArguments($args);
		return $this;
	}


	private function getServiceResultDefinition(): ServiceDefinition
	{
		return $this->resultDefinition instanceof ServiceDefinition
			? $this->resultDefinition
			: throw new Nette\InvalidStateException(sprintf(
				"Service '%s': the result definition is a %s and cannot be configured this way.",
				$this->getName(throw: false) ?? '',
				$this->resultDefinition::class,
			));
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


	public function resolveType(Nette\DI\Compiler\Resolver $resolver): void
	{
		$implement = $this->getType();
		if (!$implement) {
			throw new ServiceCreationException('Type is missing in definition of service.');
		}

		$type = Type::fromReflection(new \ReflectionMethod($implement, self::MethodCreate));
		assert($type !== null);
		$resultDef = $this->resultDefinition;
		try {
			$resolver->resolveDefinition($resultDef);
		} catch (ServiceCreationException $e) {
			if ($resultDef->getType()) {
				throw $e;
			}

			$resultDef->setType(Helpers::ensureClassType($type, "return type of $implement::" . self::MethodCreate . '()'));
			$resolver->resolveDefinition($resultDef);
		}

		if ($resultDef->getType() && !$type->allows($resultDef->getType())) {
			throw new ServiceCreationException(sprintf(
				'Factory for %s cannot create incompatible %s type.',
				$type,
				$resultDef->getType(),
			));
		}
	}


	public function complete(Nette\DI\Compiler\Resolver $resolver): void
	{
		$resultDef = $this->resultDefinition;

		if ($resultDef instanceof ServiceDefinition) {
			$this->completeParameters($resolver);
			$creator = $resultDef->getCreator();
			assert($creator instanceof Statement);
			$this->convertArguments($creator->arguments);
			foreach ($resultDef->getSetup() as $setup) {
				$this->convertArguments($setup->arguments);
			}

			if ($resultDef->getEntity() instanceof Reference && !$creator->arguments) {
				$resultDef->setCreator([ // render as $container->createMethod()
					new Reference(Nette\DI\ContainerBuilder::ThisContainer),
					Nette\DI\Container::getMethodName($resultDef->getEntity()->getValue()),
				]);
			}
		}

		$resolver->completeDefinition($resultDef);
	}


	private function completeParameters(Nette\DI\Compiler\Resolver $resolver): void
	{
		assert($this->resultDefinition instanceof ServiceDefinition);
		$interface = $this->getType();
		assert($interface !== null);
		$method = new \ReflectionMethod($interface, self::MethodCreate);

		$ctorParams = [];
		$class = $this->resultDefinition->getCreator()->resolveType($resolver);
		if ($class !== null && class_exists($class) && ($ctor = (new \ReflectionClass($class))->getConstructor())) {
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

				$this->resultDefinition->setArgument($ctorParam->getPosition(), new Php\Literal('$' . $ctorParam->name));

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


	/** @param  array<mixed>  $args */
	public function convertArguments(array &$args): void
	{
		foreach ($args as &$v) {
			if (is_string($v) && $v && $v[0] === '$') {
				$v = new Php\Literal($v);
			}
		}
	}


	public function generateCode(Nette\DI\Compiler\PhpGenerator $generator): string
	{
		$implement = $this->getType();
		assert($implement !== null);

		$class = (new Php\ClassType)
			->addImplement($implement);

		$class->addMethod('__construct')
			->addPromotedParameter('container')
				->setPrivate()
				->setType($generator->getClassName());

		$methodCreate = $class->addMethod(self::MethodCreate);
		$this->resultDefinition->generateMethod($methodCreate, $generator);
		$body = $methodCreate->getBody();
		$body = str_replace('$this', '$this->container', $body);
		$body = str_replace('$this->container->container', '$this->container', $body);

		$rm = new \ReflectionMethod($implement, self::MethodCreate);
		$methodCreate
			->setParameters(array_map((new Php\Factory)->fromParameterReflection(...), $rm->getParameters()))
			->setReturnType((string) Type::fromReflection($rm))
			->setBody($body);

		return 'return new class ($this) ' . $class . ';';
	}


	public function __clone()
	{
		parent::__clone();
		$this->resultDefinition = unserialize(serialize($this->resultDefinition));
	}
}
