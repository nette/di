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
use Nette\Utils\Arrays;
use Nette\Utils\Reflection;


/**
 * Services resolver
 * @internal
 */
class Resolver
{
	private ContainerBuilder $builder;
	private ?Definition $currentService = null;
	private ?string $currentServiceType = null;
	private bool $currentServiceAllowed = false;

	/** circular reference detector */
	private \SplObjectStorage $recursive;


	public function __construct(ContainerBuilder $builder)
	{
		$this->builder = $builder;
		$this->recursive = new \SplObjectStorage;
	}


	public function withCurrentService(Definition $definition): self
	{
		$dolly = clone $this;
		$dolly->currentService = in_array($definition, $this->builder->getDefinitions(), strict: true)
			? $definition
			: null;
		$dolly->currentServiceType = $definition->getType();
		return $dolly;
	}


	public function withCurrentServiceAvailable(): self
	{
		$dolly = clone $this;
		$dolly->currentServiceAllowed = true;
		return $dolly;
	}


	public function getCurrentService(bool $type = false): Definition|string|null
	{
		return $type ? $this->currentServiceType : $this->currentService;
	}


	public function getContainerBuilder(): ContainerBuilder
	{
		return $this->builder;
	}


	public function resolveDefinition(Definition $def): void
	{
		if ($this->recursive->contains($def)) {
			$names = array_map(fn($item) => $item->getName(), iterator_to_array($this->recursive));
			throw new ServiceCreationException(sprintf('Circular reference detected for services: %s.', implode(', ', $names)));
		}

		try {
			$this->recursive->attach($def);

			$def->resolveType($this);

			if (!$def->getType()) {
				throw new ServiceCreationException('Type of service is unknown.');
			}
		} catch (\Throwable $e) {
			throw $this->completeException($e, $def);

		} finally {
			$this->recursive->detach($def);
		}
	}


	public function completeDefinition(Definition $def): void
	{
		try {
			$def->complete($this->withCurrentService($def));

			$this->addDependency(new \ReflectionClass($def->getType()));

		} catch (\Throwable $e) {
			throw $this->completeException($e, $def);
		}
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
			&& is_a($this->currentServiceType, $type, allow_string: true)
		) {
			return new Reference(Reference::Self);
		}

		$name = $this->builder->getByType($type, throw: true);
		if (
			!$this->currentServiceAllowed
			&& $this->currentService === $this->builder->getDefinition($name)
		) {
			throw new MissingServiceException;
		}

		return new Reference($name);
	}


	/**
	 * Adds item to the list of dependencies.
	 */
	public function addDependency(\ReflectionClass|\ReflectionFunctionAbstract|string $dep): static
	{
		$this->builder->addDependency($dep);
		return $this;
	}


	/** @internal */
	public function completeException(\Throwable $e, Definition $def): ServiceCreationException
	{
		$message = $e->getMessage();
		if ($e instanceof ServiceCreationException && str_starts_with($message, '[Service ')) {
			return $e;
		}

		if ($tmp = $def->getType()) {
			$message = str_replace(" $tmp::", ' ' . preg_replace('~.*\\\\~', '', $tmp) . '::', $message);
		}

		$message = '[' . $def->getDescriptor() . "]\n" . $message;

		return $e instanceof ServiceCreationException
			? $e->setMessage($message)
			: new ServiceCreationException($message, 0, $e);
	}


	public function autowireServices(\ReflectionFunctionAbstract $method, array $arguments): array
	{
		$getter = fn(string $type, bool $single) => $single
			? $this->getByType($type)
			: array_values(array_filter($this->builder->findAutowired($type), fn($obj) => $obj !== $this->currentService));
		return self::autowireArguments($method, $arguments, $getter);
	}


	/**
	 * Add missing arguments using autowiring.
	 * @param  (callable(string, bool): (object|object[]|null))  $getter
	 * @throws ServiceCreationException
	 */
	public static function autowireArguments(
		\ReflectionFunctionAbstract $method,
		array $arguments,
		callable $getter,
	): array
	{
		$useName = false;
		$num = -1;
		$res = [];

		foreach ($method->getParameters() as $num => $param) {
			$paramName = $param->name;

			if ($param->isVariadic()) {
				if ($useName && Arrays::some($arguments, fn($val, $key) => is_int($key))) {
					throw new ServiceCreationException(sprintf(
						'Cannot use positional argument after named or omitted argument in %s.',
						Reflection::toString($param),
					));

				} elseif (array_key_exists($paramName, $arguments)) {
					if (!is_array($arguments[$paramName])) {
						throw new ServiceCreationException(sprintf(
							'Parameter %s must be array, %s given.',
							Reflection::toString($param),
							gettype($arguments[$paramName]),
						));
					}

					$res = array_merge($res, $arguments[$paramName]);
					unset($arguments[$paramName]);

				} else {
					$res = array_merge($res, $arguments);
					$arguments = [];
				}

			} elseif (array_key_exists($key = $paramName, $arguments) || array_key_exists($key = $num, $arguments)) {
				$val = $arguments[$key];
				$res[$useName ? $paramName : $num] = is_scalar($val) && $param->getAttributes(\SensitiveParameter::class)
					? ContainerBuilder::literal('/*sensitive{*/?/*}*/', [$val])
					: $val;
				unset($arguments[$key], $arguments[$num]); // unset $num to enable overwriting in configuration

			} elseif (($aw = self::autowireArgument($param, $getter)) !== null) {
				$res[$useName ? $paramName : $num] = $aw;

			} else {
				$useName = true; // is optional
			}
		}

		// extra parameters
		while (!$useName && array_key_exists(++$num, $arguments)) {
			$res[$num] = $arguments[$num];
			unset($arguments[$num]);
		}

		if ($arguments) {
			throw new ServiceCreationException(sprintf(
				'Unable to pass specified arguments to %s.',
				Reflection::toString($method),
			));
		}

		return $res;
	}


	/**
	 * Resolves missing argument using autowiring.
	 * @param  (callable(string, bool): (object|object[]|null))  $getter
	 * @throws ServiceCreationException
	 */
	private static function autowireArgument(\ReflectionParameter $parameter, callable $getter): mixed
	{
		$desc = Reflection::toString($parameter);
		$type = Nette\Utils\Type::fromReflection($parameter);

		if ($type?->isClass()) {
			$class = $type->getSingleName();
			try {
				$res = $getter($class, true);
			} catch (MissingServiceException) {
				$res = null;
			} catch (ServiceCreationException $e) {
				throw new ServiceCreationException(sprintf("%s\nRequired by %s.", $e->getMessage(), $desc), 0, $e);
			}

			if ($res !== null || $parameter->isOptional()) {
				return $res;
			} elseif (class_exists($class) || interface_exists($class)) {
				throw new ServiceCreationException(sprintf(
					"Service of type %s required by %s not found.\nDid you add it to configuration file?",
					$class,
					$desc,
				));
			} else {
				throw new ServiceCreationException(sprintf(
					"Class '%s' required by %s not found.\nCheck the parameter type and 'use' statements.",
					$class,
					$desc,
				));
			}

		} elseif ($itemType = self::isArrayOf($parameter, $type)) {
			return $getter($itemType, false);

		} elseif ($parameter->isOptional()) {
			return null;

		} else {
			throw new ServiceCreationException(sprintf(
				'Parameter %s has %s, so its value must be specified.',
				$desc,
				$type && !$type->isSimple() ? 'complex type and no default value' : 'no class type or default value',
			));
		}
	}


	private static function isArrayOf(\ReflectionParameter $parameter, ?Nette\Utils\Type $type): ?string
	{
		$method = $parameter->getDeclaringFunction();
		return $method instanceof \ReflectionMethod
			&& $type?->getSingleName() === 'array'
			&& preg_match(
				'#@param[ \t]+(?|([\w\\\\]+)\[\]|list<([\w\\\\]+)>|array<int,\s*([\w\\\\]+)>)[ \t]+\$' . $parameter->name . '#',
				(string) $method->getDocComment(),
				$m,
			)
			&& ($itemType = Reflection::expandClassName($m[1], $method->getDeclaringClass()))
			&& (class_exists($itemType) || interface_exists($itemType))
				? $itemType
				: null;
	}


	/** @deprecated */
	public function resolveReferenceType(Reference $ref): ?string
	{
		return $ref->resolveType($this);
	}


	/** @deprecated */
	public function resolveEntityType(Statement $statement): ?string
	{
		return $statement->resolveType($this);
	}


	/** @deprecated */
	public function resolveReference(Reference $ref): Definition
	{
		return $ref->isSelf()
			? $this->currentService
			: $this->builder->getDefinition($ref->getValue());
	}


	/** @deprecated */
	public function normalizeReference(Reference $ref): Reference
	{
		$ref->complete($this);
		return $ref;
	}


	/** @deprecated */
	public function completeStatement(Statement $statement, bool $currentServiceAllowed = false): Statement
	{
		$resolver = $this->withCurrentService($this->currentService);
		$resolver->currentServiceAllowed = $currentServiceAllowed;
		$statement->complete($resolver);
		return $statement;
	}


	/** @deprecated */
	public function completeArguments(array $arguments): array
	{
		return (new Statement(null, $arguments))->completeArguments($this, $arguments);
	}
}
