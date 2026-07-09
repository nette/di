<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Compiler;

use Nette;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definition;
use Nette\DI\Definitions;
use Nette\DI\Definitions\Statement;
use Nette\DI\Expression;
use Nette\DI\Expressions;
use Nette\DI\Expressions\Reference;
use Nette\DI\MissingServiceException;
use Nette\DI\NotAllowedDuringResolvingException;
use Nette\DI\ServiceCreationException;
use Nette\Utils\Arrays;
use Nette\Utils\Reflection;
use function array_key_exists, gettype, in_array, is_array, is_int, is_scalar, is_string, sprintf, strlen;


/**
 * Resolves and completes service definitions, including autowiring of arguments.
 * @internal
 */
class Resolver
{
	private ?Definition $currentService = null;
	private ?string $currentServiceType = null;
	private bool $currentServiceAllowed = false;

	/** @var \SplObjectStorage<Definition, true> circular reference detector */
	private \SplObjectStorage $recursive;


	public function __construct(
		private readonly ContainerBuilder $builder,
	) {
		$this->recursive = new \SplObjectStorage;
	}


	/** @internal used by definition completion and unit tests */
	public function withCurrentService(Definition $definition): self
	{
		$dolly = clone $this;
		$dolly->currentService = $definition;
		$dolly->currentServiceType = $definition->getType();
		$dolly->currentServiceAllowed = false;
		return $dolly;
	}


	public function withCurrentServiceAvailable(): self
	{
		$dolly = clone $this;
		$dolly->currentServiceAllowed = true;
		return $dolly;
	}


	public function getContainerBuilder(): ContainerBuilder
	{
		return $this->builder;
	}


	public function getCurrentService(): ?Definition
	{
		return $this->currentService;
	}


	public function getCurrentServiceType(): ?string
	{
		return $this->currentServiceType;
	}


	/**
	 * Resolves the service type for the given definition.
	 */
	public function resolveDefinition(Definition $def): void
	{
		if (isset($this->recursive[$def])) {
			$names = array_map(fn($item) => $item->getName(throw: false) ?? '?', iterator_to_array($this->recursive));
			throw new ServiceCreationException(sprintf('Circular reference detected for services: %s.', implode(', ', $names)));
		}

		try {
			$this->recursive[$def] = true;

			$def->resolveType($this);
			if (!$def->getType()) {
				throw new ServiceCreationException('Type of service is unknown.');
			}
		} catch (\Throwable $e) {
			throw $this->completeException($e, $def);

		} finally {
			unset($this->recursive[$def]);
		}
	}


	/**
	 * Completes the service definition by resolving and autowiring all its arguments.
	 */
	public function completeDefinition(Definition $def): void
	{
		try {
			$def->complete($this->withCurrentService($def));

			if ($type = $def->getType()) {
				$this->addDependency(new \ReflectionClass($type));
			}

		} catch (\Throwable $e) {
			throw $this->completeException($e, $def);
		}
	}


	/**
	 * Resolves an argument array: normalizes the @reference syntax, completes nested expressions.
	 * @param  array<mixed>  $arguments
	 * @return array<mixed>
	 */
	public function resolveArguments(array $arguments, string $usedIn): array
	{
		try {
			$arguments = $this->convertReferences($arguments);
			return $this->completeArguments($arguments);
		} catch (ServiceCreationException $e) {
			if (!str_contains($e->getMessage(), ' (used in')) {
				$usedIn = str_replace('@self', '@' . $this->currentService?->getName(throw: false), $usedIn);
				$e->setMessage($e->getMessage() . " (used in $usedIn)");
			}

			throw $e;
		}
	}


	/**
	 * Completes and autowires the given arguments; expands typed() and tagged() markers.
	 * @param  array<mixed>  $arguments
	 * @return array<mixed>
	 */
	public function completeArguments(array $arguments): array
	{
		array_walk_recursive($arguments, function (&$val): void {
			if ($val instanceof Statement && ($val->getEntity() === 'typed' || $val->getEntity() === 'tagged')) {
				$node = $val->getEntity() === 'typed'
					? new Expressions\ServiceCollection(types: $val->arguments)
					: new Expressions\ServiceCollection(tags: $val->arguments);
				$val = $node->complete($this)->references ?? []; // node is replaced with array for back compatibility

			} elseif ($val instanceof Expression) {
				$val = $val->complete($this);

			} elseif ($val instanceof Definition) {
				if ($this->builder->getDefinition($val->getName()) !== $val) {
					throw new ServiceCreationException(sprintf("Service '%s' does not match the expected service.", $val->getName()));
				}

				$val = (new Reference($val->getName()))->complete($this);
			}
		});
		return $arguments;
	}


	/**
	 * Returns named reference to service resolved by type (or 'self' reference for local-autowiring).
	 * @param class-string  $type
	 * @throws ServiceCreationException when multiple found
	 * @throws MissingServiceException when not found
	 * @throws NotAllowedDuringResolvingException
	 */
	public function getByType(string $type): Reference
	{
		if (
			$this->currentService
			&& $this->currentServiceAllowed
			&& $this->currentServiceType !== null
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
	 * @param  \ReflectionClass<object>|\ReflectionFunctionAbstract|string  $dep
	 */
	public function addDependency(\ReflectionClass|\ReflectionFunctionAbstract|string $dep): static
	{
		$this->builder->addDependency($dep);
		return $this;
	}


	/** @internal */
	public function completeException(\Throwable $e, ?Definition $def): ServiceCreationException
	{
		if ($e instanceof ServiceCreationException && str_starts_with($e->getMessage(), "Service '")) {
			return $e;
		}

		if (!$def) {
			return $e instanceof ServiceCreationException
				? $e
				: new ServiceCreationException($e->getMessage(), 0, $e);
		}

		$name = $def->getName(throw: false);
		$type = $def->getType();
		if ($name && !ctype_digit($name)) {
			$message = "Service '$name'" . ($type ? " (type of $type)" : '') . ': ';
		} elseif ($type) {
			$message = "Service of type $type: ";
		} elseif ($def instanceof Definitions\ServiceDefinition && $def->getEntity()) {
			$message = 'Service (' . $this->entityToString($def->getEntity()) . '): ';
		} else {
			$message = '';
		}

		$message .= $type
			? str_replace("$type::", preg_replace('~.*\\\~', '', $type) . '::', $e->getMessage())
			: $e->getMessage();

		return $e instanceof ServiceCreationException
			? $e->setMessage($message)
			: new ServiceCreationException($message, 0, $e);
	}


	/**
	 * Formats a ServiceDefinition entity (as returned by getEntity()) for an error message.
	 * @param  string|array{string|Expression, string}|Reference|null  $entity
	 */
	private function entityToString($entity): string
	{
		if (is_string($entity)) {
			return $entity . '::__construct()';
		} elseif ($entity instanceof Reference) {
			return '@' . $entity->getValue();
		} elseif (is_array($entity)) {
			if (!str_contains($entity[1], '$')) {
				$entity[1] .= '()';
			}

			if ($entity[0] instanceof Reference) {
				$entity[0] = '@' . $entity[0]->getValue();
			} elseif (!is_string($entity[0])) {
				return $entity[1];
			}

			return implode('::', $entity);
		}

		return (string) $entity;
	}


	/**
	 * Converts @service and @service::property strings in arguments to References and Statements.
	 * @param  array<mixed>  $arguments
	 * @return array<mixed>
	 */
	private function convertReferences(array $arguments): array
	{
		array_walk_recursive($arguments, function (&$val): void {
			if (is_string($val) && strlen($val) > 1 && $val[0] === '@' && $val[1] !== '@') {
				$pair = explode('::', substr($val, 1), 2);
				if (!isset($pair[1])) { // @service
					$val = new Reference($pair[0]);
				} elseif (preg_match('#^[A-Z][a-zA-Z0-9_]*$#D', $pair[1])) { // @service::CONSTANT
					$val = ContainerBuilder::literal((new Reference($pair[0]))->resolveType($this) . '::' . $pair[1]);
				} else { // @service::property
					$val = new Expressions\PropertyAccess(new Reference($pair[0]), $pair[1]);
				}
			} elseif (is_string($val) && str_starts_with($val, '@@')) { // escaped text @@
				$val = substr($val, 1);
			}
		});
		return $arguments;
	}


	/**
	 * Add missing arguments using autowiring.
	 * @param  array<mixed>  $arguments
	 * @param  (callable(string, bool): (object|object[]|null))|self  $getter
	 * @return array<mixed>
	 * @throws ServiceCreationException
	 */
	public static function autowireArguments(
		\ReflectionFunctionAbstract $method,
		array $arguments,
		callable|self $getter,
	): array
	{
		if ($getter instanceof self) {
			$resolver = $getter;
			$getter = fn(string $type, bool $single) => $single
				? $resolver->getByType($type)
				: array_values(array_filter($resolver->builder->findAutowired($type), fn($obj) => $obj !== $resolver->currentService));
		}

		// an open slot (DSL wire()) is treated as an omitted argument to be autowired; keys keep positions
		$arguments = array_filter($arguments, fn($val): bool => $val !== Expressions\ArgumentPlaceholder::Single);

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
				throw new ServiceCreationException("{$e->getMessage()} (required by $desc)", 0, $e);
			}

			if ($res !== null || $parameter->isOptional()) {
				return $res;
			} elseif (class_exists($class) || interface_exists($class)) {
				throw new ServiceCreationException(sprintf(
					'Service of type %s required by %s not found. Did you add it to configuration file?',
					$class,
					$desc,
				));
			} else {
				throw new ServiceCreationException(sprintf(
					"Class '%s' required by %s not found. Check the parameter type and 'use' statements.",
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
				'#@param[ \t]+(?|([\w\\\]+)\[\]|list<([\w\\\]+)>|array<int,\s*([\w\\\]+)>)[ \t]+\$' . $parameter->name . '#',
				(string) $method->getDocComment(),
				$m,
			)
			&& ($itemType = Reflection::expandClassName($m[1], $method->getDeclaringClass()))
			&& (class_exists($itemType) || interface_exists($itemType))
				? $itemType
				: null;
	}


	/** @deprecated use Reference::resolveType() */
	public function resolveReferenceType(Reference $ref): ?string
	{
		return $ref->resolveType($this);
	}


	/** @deprecated use Statement::resolveType() */
	public function resolveEntityType(Statement $statement): ?string
	{
		return $statement->resolveType($this);
	}


	/** @deprecated use Statement::complete() */
	public function completeStatement(Statement $statement): Expression
	{
		return $statement->complete($this);
	}


	/** @deprecated use Reference::complete() */
	public function normalizeReference(Reference $ref): Reference
	{
		return $ref->complete($this);
	}


	/** @deprecated */
	public function resolveReference(Reference $ref): Definition
	{
		if ($ref->isSelf()) {
			assert($this->currentService !== null);
			return $this->currentService;
		}

		return $this->builder->getDefinition($ref->getValue());
	}
}
