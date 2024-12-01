<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI;

use Nette;
use Nette\DI\Definitions\Definition;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\Statement;
use Nette\PhpGenerator\Helpers as PhpHelpers;
use Nette\Utils\Arrays;
use Nette\Utils\Callback;
use Nette\Utils\Reflection;
use Nette\Utils\Validators;
use function array_filter, array_key_exists, array_map, array_merge, array_values, array_walk_recursive, class_exists, count, ctype_digit, explode, function_exists, gettype, implode, in_array, interface_exists, is_a, is_array, is_int, is_scalar, is_string, iterator_to_array, ltrim, preg_match, preg_replace, sprintf, str_contains, str_ends_with, str_replace, str_starts_with, strlen, substr;


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


	public function getContainerBuilder(): ContainerBuilder
	{
		return $this->builder;
	}


	/**
	 * Resolves the service type for the given definition.
	 */
	public function resolveDefinition(Definition $def): void
	{
		if (isset($this->recursive[$def])) {
			$names = array_map(fn($item) => $item->getName(), iterator_to_array($this->recursive));
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
	 * Returns the class name that the given reference points to, or null if not resolvable.
	 */
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


	/**
	 * Returns the class name produced by the given statement's entity, or null if not resolvable.
	 */
	public function resolveEntityType(Statement $statement): ?string
	{
		$entity = $this->normalizeEntity($statement);

		if ($statement->arguments === self::getFirstClassCallable()) {
			return \Closure::class;

		} elseif (is_array($entity)) {
			if ($entity[0] instanceof Reference || $entity[0] instanceof Statement) {
				$entity[0] = $this->resolveEntityType($entity[0] instanceof Statement ? $entity[0] : new Statement($entity[0]));
				if (!$entity[0]) {
					return null;
				}
			}

			try {
				$reflection = Callback::toReflection($entity[0] === '' ? $entity[1] : $entity);
				$refClass = $reflection instanceof \ReflectionMethod
					? $reflection->getDeclaringClass()
					: null;
			} catch (\ReflectionException $e) {
				$refClass = $reflection = null;
			}

			if (isset($e)) {
				throw new ServiceCreationException(sprintf('Method %s() is not callable.', Callback::toString($entity)), 0, $e);
			} elseif ($reflection instanceof \ReflectionMethod && $refClass && (!$reflection->isPublic()
				|| ($refClass->isTrait() && !$reflection->isStatic())
			)) {
				throw new ServiceCreationException(sprintf('Method %s() is not callable.', Callback::toString($entity)));
			}

			$this->addDependency($reflection);

			return ($type = Nette\Utils\Type::fromReflection($reflection)) && !in_array($type->getSingleName(), ['object', 'mixed'], strict: true)
				? Helpers::ensureClassType(
					$type,
					sprintf('return type of %s()', Callback::toString($entity)),
					allowNullable: true,
				)
				: null;

		} elseif ($entity instanceof Reference) { // alias or factory
			return $this->resolveReferenceType($entity);

		} elseif (is_string($entity)) { // class
			if (!class_exists($entity)) {
				throw new ServiceCreationException(sprintf(
					interface_exists($entity)
						? "Interface %s can not be used as 'create' or 'factory', did you mean 'implement'?"
						: "Class '%s' not found.",
					$entity,
				));
			}

			return $entity;
		}

		return null;
	}


	/**
	 * Completes the service definition by resolving and autowiring all its arguments.
	 */
	public function completeDefinition(Definition $def): void
	{
		$this->currentService = in_array($def, $this->builder->getDefinitions(), strict: true)
			? $def
			: null;
		$this->currentServiceType = $def->getType();
		$this->currentServiceAllowed = false;

		try {
			$def->complete($this);

			if ($type = $def->getType()) {
				$this->addDependency(new \ReflectionClass($type));
			}

		} catch (\Throwable $e) {
			throw $this->completeException($e, $def);

		} finally {
			$this->currentService = $this->currentServiceType = null;
		}
	}


	/**
	 * Resolves and autowires a statement's entity and arguments into a completed Statement.
	 */
	public function completeStatement(Statement $statement, bool $currentServiceAllowed = false): Statement
	{
		$this->currentServiceAllowed = $currentServiceAllowed;
		$entity = $this->normalizeEntity($statement);
		$arguments = $this->convertReferences($statement->arguments);
		$getter = fn(string $type, bool $single) => $single
				? $this->getByType($type)
				: array_values(array_filter($this->builder->findAutowired($type), fn($obj) => $obj !== $this->currentService));

		switch (true) {
			case $statement->arguments === self::getFirstClassCallable():
				if (!is_array($entity) || !PhpHelpers::isIdentifier($entity[1])) {
					throw new ServiceCreationException(sprintf('Cannot create closure for %s(...)', $entity));
				}
				if ($entity[0] instanceof Statement) {
					$entity[0] = $this->completeStatement($entity[0], $this->currentServiceAllowed);
				}
				break;

			case is_string($entity) && str_contains($entity, '?'): // PHP literal
				break;

			case $entity === 'not':
				if (count($arguments) !== 1) {
					throw new ServiceCreationException(sprintf('Function %s() expects 1 parameter, %s given.', $entity, count($arguments)));
				}

				$entity = ['', '!'];
				break;

			case $entity === 'bool':
			case $entity === 'int':
			case $entity === 'float':
			case $entity === 'string':
				if (count($arguments) !== 1) {
					throw new ServiceCreationException(sprintf('Function %s() expects 1 parameter, %s given.', $entity, count($arguments)));
				}

				$arguments = [$arguments[0], $entity];
				$entity = [Helpers::class, 'convertType'];
				break;

			case is_string($entity): // create class
				if (!class_exists($entity)) {
					throw new ServiceCreationException(sprintf("Class '%s' not found.", $entity));
				} elseif ((new \ReflectionClass($entity))->isAbstract()) {
					throw new ServiceCreationException(sprintf('Class %s is abstract.', $entity));
				} elseif (($rm = (new \ReflectionClass($entity))->getConstructor()) !== null && !$rm->isPublic()) {
					throw new ServiceCreationException(sprintf('Class %s has %s constructor.', $entity, $rm->isProtected() ? 'protected' : 'private'));
				} elseif ($constructor = (new \ReflectionClass($entity))->getConstructor()) {
					$arguments = self::autowireArguments($constructor, $arguments, $getter);
					$this->addDependency($constructor);
				} elseif ($arguments) {
					throw new ServiceCreationException(sprintf(
						'Unable to pass arguments, class %s has no constructor.',
						$entity,
					));
				}

				break;

			case $entity instanceof Reference:
				if ($arguments) {
					$e = $this->completeException(new ServiceCreationException(sprintf('Parameters were passed to reference @%s, although references cannot have any parameters.', $entity->getValue())), $this->currentService);
					trigger_error($e->getMessage(), E_USER_DEPRECATED);
				}
				$entity = [new Reference(ContainerBuilder::ThisContainer), Container::getMethodName($entity->getValue())];
				break;

			case is_array($entity):
				if (!preg_match('#^\$?(\\\?' . PhpHelpers::ReIdentifier . ')+(\[\])?$#D', $entity[1])) {
					throw new ServiceCreationException(sprintf(
						"Expected function, method or property name, '%s' given.",
						$entity[1],
					));
				}

				switch (true) {
					case $entity[0] === '': // function call
						if (!function_exists($entity[1])) {
							throw new ServiceCreationException(sprintf("Function %s doesn't exist.", $entity[1]));
						}

						$rf = new \ReflectionFunction($entity[1]);
						$arguments = self::autowireArguments($rf, $arguments, $getter);
						$this->addDependency($rf);
						break;

					case $entity[0] instanceof Statement:
						$entity[0] = $this->completeStatement($entity[0], $this->currentServiceAllowed);
						// break omitted

					case is_string($entity[0]): // static method call
					case $entity[0] instanceof Reference:
						if ($entity[1][0] === '$') { // property getter, setter or appender
							Validators::assert($arguments, 'list:0..1', "setup arguments for '" . Callback::toString($entity) . "'");
							if (!$arguments && str_ends_with($entity[1], '[]')) {
								throw new ServiceCreationException(sprintf('Missing argument for %s.', $entity[1]));
							}
						} elseif (
							$type = $entity[0] instanceof Reference
								? $this->resolveReferenceType($entity[0])
								: $this->resolveEntityType($entity[0] instanceof Statement ? $entity[0] : new Statement($entity[0]))
						) {
							$rc = new \ReflectionClass($type);
							if ($rc->hasMethod($entity[1])) {
								$rm = $rc->getMethod($entity[1]);
								if (!$rm->isPublic()) {
									throw new ServiceCreationException(sprintf('%s::%s() is not callable.', $type, $entity[1]));
								}

								$arguments = self::autowireArguments($rm, $arguments, $getter);
								$this->addDependency($rm);
							}
						}
				}
		}

		try {
			$arguments = $this->completeArguments($arguments);
		} catch (ServiceCreationException $e) {
			if (!str_contains($e->getMessage(), ' (used in')) {
				$e->setMessage($e->getMessage() . " (used in {$this->entityToString($entity)})");
			}

			throw $e;
		}

		return new Statement($entity, $arguments);
	}


	/**
	 * @param  array<mixed>  $arguments
	 * @return array<mixed>
	 */
	public function completeArguments(array $arguments): array
	{
		array_walk_recursive($arguments, function (&$val): void {
			if ($val instanceof Statement) {
				$entity = $val->getEntity();
				if ($entity === 'typed' || $entity === 'tagged') {
					$services = [];
					$current = $this->currentService?->getName();
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
	 * Returns literal, Class, Reference, [Class, member], [, globalFunc], [Reference, member], [Statement, member]
	 * @return string|array{string|Reference|Statement, string}|Reference|null
	 */
	private function normalizeEntity(Statement $statement): string|array|Reference|null
	{
		$entity = $statement->getEntity();
		if (is_array($entity)) {
			$item = &$entity[0];
		} else {
			$item = &$entity;
		}

		if ($item instanceof Definition) {
			if ($this->builder->getDefinition($item->getName()) !== $item) {
				throw new ServiceCreationException(sprintf("Service '%s' does not match the expected service.", $item->getName()));

			}
			$item = new Reference($item->getName());
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
				throw new ServiceCreationException(sprintf("Reference to missing service '%s'.", $service));
			}

			return $this->currentService && $service === $this->currentService->getName()
				? new Reference(Reference::Self)
				: $ref;
		}

		try {
			return $this->getByType($service);
		} catch (NotAllowedDuringResolvingException) {
			return new Reference($service);
		}
	}


	/**
	 * Returns the definition that the reference points to.
	 */
	public function resolveReference(Reference $ref): Definition
	{
		if ($ref->isSelf()) {
			assert($this->currentService !== null);
			return $this->currentService;
		}
		return $this->builder->getDefinition($ref->getValue());
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


	private function completeException(\Throwable $e, ?Definition $def): ServiceCreationException
	{
		if ($e instanceof ServiceCreationException && str_starts_with($e->getMessage(), "Service '")) {
			return $e;
		}

		if (!$def) {
			return $e instanceof ServiceCreationException
				? $e
				: new ServiceCreationException($e->getMessage(), 0, $e);
		}

		$name = $def->getName();
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


	/** @param  mixed  $entity */
	private function entityToString($entity): string
	{
		$referenceToText = fn(Reference $ref): string => $ref->isSelf() && $this->currentService
				? '@' . $this->currentService->getName()
				: '@' . $ref->getValue();
		if (is_string($entity)) {
			return $entity . '::__construct()';
		} elseif ($entity instanceof Reference) {
			$entity = $referenceToText($entity);
		} elseif (is_array($entity)) {
			if (!str_contains($entity[1], '$')) {
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


	/**
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
					$val = ContainerBuilder::literal($this->resolveReferenceType(new Reference($pair[0])) . '::' . $pair[1]);
				} else { // @service::property
					$val = new Statement([new Reference($pair[0]), '$' . $pair[1]]);
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
	 * @param  (callable(string, bool): (object|object[]|null))  $getter
	 * @return array<mixed>
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


	/**
	 * Returns the sentinel value used to mark first-class callable syntax (...).
	 * @return list<mixed>
	 * @internal
	 */
	public static function getFirstClassCallable(): array
	{
		static $x = [new Nette\PhpGenerator\Literal('...')];
		return $x;
	}
}
