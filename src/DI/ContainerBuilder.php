<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI;

use Nette;
use Nette\Utils\Validators;
use Nette\Utils\Strings;
use Nette\PhpGenerator\Helpers as PhpHelpers;
use ReflectionClass;


/**
 * Basic container builder.
 */
class ContainerBuilder
{
	use Nette\SmartObject;

	const THIS_SERVICE = 'self',
		THIS_CONTAINER = 'container';

	/** @var array */
	public $parameters = [];

	/** @var string */
	private $className = 'Container';

	/** @var ServiceDefinition[] */
	private $definitions = [];

	/** @var bool */
	private $classListObsolete = TRUE;

	/** @var array of alias => service */
	private $aliases = [];

	/** @var array for auto-wiring */
	private $classes = FALSE;

	/** @var string[] of classes excluded from auto-wiring */
	private $excludedClasses = [];

	/** @var array of file names */
	private $dependencies = [];

	/** @var Nette\PhpGenerator\ClassType[] */
	private $generatedClasses = [];

	/** @var string */
	private $currentService;


	/**
	 * Adds new service definition.
	 * @param  string
	 * @return ServiceDefinition
	 */
	public function addDefinition($name, ServiceDefinition $definition = NULL)
	{
		$this->classListObsolete = TRUE;
		if (!is_string($name) || !$name) { // builder is not ready for falsy names such as '0'
			throw new Nette\InvalidArgumentException(sprintf('Service name must be a non-empty string, %s given.', gettype($name)));
		}
		$name = isset($this->aliases[$name]) ? $this->aliases[$name] : $name;
		if (isset($this->definitions[$name])) {
			throw new Nette\InvalidStateException("Service '$name' has already been added.");
		}
		if (!$definition) {
			$definition = new ServiceDefinition();
		}
		$definition->setContainerBuilder($this);
		return $this->definitions[$name] = $definition ;
	}


	/**
	 * Removes the specified service definition.
	 * @param  string
	 * @return void
	 */
	public function removeDefinition($name)
	{
		$this->classListObsolete = TRUE;
		$name = isset($this->aliases[$name]) ? $this->aliases[$name] : $name;
		unset($this->definitions[$name]);
	}


	/**
	 * Gets the service definition.
	 * @param  string
	 * @return ServiceDefinition
	 */
	public function getDefinition($name)
	{
		$service = isset($this->aliases[$name]) ? $this->aliases[$name] : $name;
		if (!isset($this->definitions[$service])) {
			throw new MissingServiceException("Service '$name' not found.");
		}
		return $this->definitions[$service];
	}


	/**
	 * Gets all service definitions.
	 * @return ServiceDefinition[]
	 */
	public function getDefinitions()
	{
		return $this->definitions;
	}


	/**
	 * Does the service definition or alias exist?
	 * @param  string
	 * @return bool
	 */
	public function hasDefinition($name)
	{
		$name = isset($this->aliases[$name]) ? $this->aliases[$name] : $name;
		return isset($this->definitions[$name]);
	}


	/**
	 * @param  string
	 * @param  string
	 */
	public function addAlias($alias, $service)
	{
		if (!is_string($alias) || !$alias) { // builder is not ready for falsy names such as '0'
			throw new Nette\InvalidArgumentException(sprintf('Alias name must be a non-empty string, %s given.', gettype($alias)));

		} elseif (!is_string($service) || !$service) { // builder is not ready for falsy names such as '0'
			throw new Nette\InvalidArgumentException(sprintf('Service name must be a non-empty string, %s given.', gettype($service)));

		} elseif (isset($this->aliases[$alias])) {
			throw new Nette\InvalidStateException("Alias '$alias' has already been added.");

		} elseif (isset($this->definitions[$alias])) {
			throw new Nette\InvalidStateException("Service '$alias' has already been added.");

		}
		$this->aliases[$alias] = $service;
	}


	/**
	 * Removes the specified alias.
	 * @return void
	 */
	public function removeAlias($alias)
	{
		unset($this->aliases[$alias]);
	}


	/**
	 * Gets all service aliases.
	 * @return array
	 */
	public function getAliases()
	{
		return $this->aliases;
	}


	/**
	 * @return self
	 */
	public function setClassName($name)
	{
		$this->className = (string) $name;
		return $this;
	}


	/**
	 * @return string
	 */
	public function getClassName()
	{
		return $this->className;
	}


	/********************* class resolving ****************d*g**/


	/**
	 * Resolves service name by type.
	 * @param  string  class or interface
	 * @return string|NULL  service name or NULL
	 * @throws ServiceCreationException
	 */
	public function getByType($class)
	{
		$class = ltrim($class, '\\');

		if ($this->currentService !== NULL) {
			$curClass = $this->definitions[$this->currentService]->getClass();
			if ($curClass === $class || is_subclass_of($curClass, $class)) {
				return $this->currentService;
			}
		}

		$classes = $this->getClassList();
		if (empty($classes[$class][TRUE])) {
			self::checkCase($class);
			return;

		} elseif (count($classes[$class][TRUE]) === 1) {
			return $classes[$class][TRUE][0];

		} else {
			$list = $classes[$class][TRUE];
			$hint = count($list) === 2 && ($tmp = strpos($list[0], '.') xor strpos($list[1], '.'))
				? '. If you want to overwrite service ' . $list[$tmp ? 0 : 1] . ', give it proper name.'
				: '';
			throw new ServiceCreationException("Multiple services of type $class found: " . implode(', ', $list) . $hint);
		}
	}


	/**
	 * Gets the service names and definitions of the specified type.
	 * @param  string
	 * @return ServiceDefinition[]
	 */
	public function findByType($class)
	{
		$class = ltrim($class, '\\');
		self::checkCase($class);
		$found = [];
		$classes = $this->getClassList();
		if (!empty($classes[$class])) {
			foreach (array_merge(...array_values($classes[$class])) as $name) {
				$found[$name] = $this->definitions[$name];
			}
		}
		return $found;
	}


	/**
	 * Gets the service objects of the specified tag.
	 * @param  string
	 * @return array of [service name => tag attributes]
	 */
	public function findByTag($tag)
	{
		$found = [];
		foreach ($this->definitions as $name => $def) {
			if (($tmp = $def->getTag($tag)) !== NULL) {
				$found[$name] = $tmp;
			}
		}
		return $found;
	}


	private function getClassList()
	{
		if ($this->classes !== FALSE && $this->classListObsolete) {
			$this->prepareClassList();
			$this->classListObsolete = FALSE;
		}
		return $this->classes ?: [];
	}


	/**
	 * Creates a list of arguments using autowiring.
	 * @return array
	 */
	public function autowireArguments($class, $method, array $arguments)
	{
		$rc = new ReflectionClass($class);
		if (!$rc->hasMethod($method)) {
			if (!Nette\Utils\Arrays::isList($arguments)) {
				throw new ServiceCreationException("Unable to pass specified arguments to $class::$method().");
			}
			return $arguments;
		}

		$rm = $rc->getMethod($method);
		if (!$rm->isPublic()) {
			throw new ServiceCreationException("$class::$method() is not callable.");
		}
		$this->addDependency((string) $rm->getFileName());
		return Helpers::autowireArguments($rm, $arguments, $this);
	}


	/**
	 * Generates $dependencies, $classes and normalizes class names.
	 * @return array
	 * @internal
	 */
	public function prepareClassList()
	{
		unset($this->definitions[self::THIS_CONTAINER]);
		$this->addDefinition(self::THIS_CONTAINER)->setClass(Container::class);

		$this->classes = FALSE;

		foreach ($this->definitions as $name => $def) {
			// prepare generated factories
			if ($def->getImplement()) {
				$this->resolveImplement($def, $name);
			}

			if ($def->isDynamic()) {
				if (!$def->getClass()) {
					throw new ServiceCreationException("Class is missing in definition of service '$name'.");
				}
				$def->setFactory(NULL);
				continue;
			}

			// complete class-factory pairs
			if (!$def->getEntity()) {
				if (!$def->getClass()) {
					throw new ServiceCreationException("Class and factory are missing in definition of service '$name'.");
				}
				$def->setFactory($def->getClass(), ($factory = $def->getFactory()) ? $factory->arguments : []);
			}

			// auto-disable autowiring for aliases
			if (($alias = $this->getServiceName($def->getFactory()->getEntity())) &&
				(!$def->getImplement() || (!Strings::contains($alias, '\\') && $this->definitions[$alias]->getImplement()))
			) {
				$def->setAutowired(FALSE);
			}
		}

		// resolve and check classes
		foreach ($this->definitions as $name => $def) {
			$this->resolveServiceClass($name);
		}

		//  build auto-wiring list
		$excludedClasses = [];
		foreach ($this->excludedClasses as $class) {
			if (class_exists($class) || interface_exists($class)) {
				self::checkCase($class);
				$excludedClasses += class_parents($class) + class_implements($class) + [$class => $class];
			}
		}

		$this->classes = [];
		foreach ($this->definitions as $name => $def) {
			if ($class = $def->getImplement() ?: $def->getClass()) {
				foreach (class_parents($class) + class_implements($class) + [$class] as $parent) {
					$this->classes[$parent][$def->isAutowired() && empty($excludedClasses[$parent])][] = (string) $name;
				}
			}
		}

		foreach ($this->classes as $class => $foo) {
			$this->addDependency((string) (new ReflectionClass($class))->getFileName());
		}
	}


	private function resolveImplement(ServiceDefinition $def, $name)
	{
		$interface = $def->getImplement();
		if (!interface_exists($interface)) {
			throw new ServiceCreationException("Interface $interface used in service '$name' not found.");
		}
		self::checkCase($interface);
		$rc = new ReflectionClass($interface);
		$method = $rc->hasMethod('create')
			? $rc->getMethod('create')
			: ($rc->hasMethod('get') ? $rc->getMethod('get') : NULL);

		if (count($rc->getMethods()) !== 1 || !$method || $method->isStatic()) {
			throw new ServiceCreationException("Interface $interface used in service '$name' must have just one non-static method create() or get().");
		}
		$def->setImplementType($methodName = $rc->hasMethod('create') ? 'create' : 'get');

		if (!$def->getClass() && !$def->getEntity()) {
			$returnType = PhpReflection::getReturnType($method);
			if (!$returnType) {
				throw new ServiceCreationException("Method $interface::$methodName() used in service '$name' has no @return annotation.");
			} elseif (!class_exists($returnType)) {
				throw new ServiceCreationException("Check a @return annotation of the $interface::$methodName() method used in service '$name', class '$returnType' cannot be found.");
			}
			$def->setClass($returnType);
		}

		if ($methodName === 'get') {
			if ($method->getParameters()) {
				throw new ServiceCreationException("Method $interface::get() used in service '$name' must have no arguments.");
			}
			if (!$def->getEntity()) {
				$def->setFactory('@\\' . ltrim($def->getClass(), '\\'));
			} elseif (!$this->getServiceName($def->getFactory()->getEntity())) {
				throw new ServiceCreationException("Invalid factory in service '$name' definition.");
			}
		}

		if (!$def->parameters) {
			$ctorParams = [];
			if (!$def->getEntity()) {
				$def->setFactory($def->getClass(), $def->getFactory() ? $def->getFactory()->arguments : []);
			}
			if (($class = $this->resolveEntityClass($def->getFactory(), [$name => 1]))
				&& ($ctor = (new ReflectionClass($class))->getConstructor())
			) {
				foreach ($ctor->getParameters() as $param) {
					$ctorParams[$param->getName()] = $param;
				}
			}

			foreach ($method->getParameters() as $param) {
				$hint = PhpReflection::getParameterType($param);
				if (isset($ctorParams[$param->getName()])) {
					$arg = $ctorParams[$param->getName()];
					if ($hint !== PhpReflection::getParameterType($arg)) {
						throw new ServiceCreationException("Type hint for \${$param->getName()} in $interface::$methodName() doesn't match type hint in $class constructor.");
					}
					$def->getFactory()->arguments[$arg->getPosition()] = self::literal('$' . $arg->getName());
				} elseif (!$def->getSetup()) {
					$hint = Nette\Utils\ObjectMixin::getSuggestion(array_keys($ctorParams), $param->getName());
					throw new ServiceCreationException("Unused parameter \${$param->getName()} when implementing method $interface::$methodName()" . ($hint ? ", did you mean \${$hint}?" : '.'));
				}
				$paramDef = $hint . ' ' . $param->getName();
				if ($param->isOptional()) {
					$def->parameters[$paramDef] = $param->getDefaultValue();
				} else {
					$def->parameters[] = $paramDef;
				}
			}
		}
	}


	/** @return string|NULL */
	private function resolveServiceClass($name, $recursive = [])
	{
		if (isset($recursive[$name])) {
			throw new ServiceCreationException(sprintf('Circular reference detected for services: %s.', implode(', ', array_keys($recursive))));
		}
		$recursive[$name] = TRUE;

		$def = $this->definitions[$name];
		$class = $def->getFactory() ? $this->resolveEntityClass($def->getFactory()->getEntity(), $recursive) : NULL; // call always to check entities
		if ($class = $def->getClass() ?: $class) {
			$def->setClass($class);
			if (!class_exists($class) && !interface_exists($class)) {
				throw new ServiceCreationException("Type $class used in service '$name' not found or is not class or interface.");
			}
			self::checkCase($class);

		} elseif ($def->isAutowired()) {
			trigger_error("Type of service '$name' is unknown.", E_USER_NOTICE);
		}
		return $class;
	}


	/** @return string|NULL */
	private function resolveEntityClass($entity, $recursive = [])
	{
		$entity = $this->normalizeEntity($entity instanceof Statement ? $entity->getEntity() : $entity);

		if (is_array($entity)) {
			if (($service = $this->getServiceName($entity[0])) || $entity[0] instanceof Statement) {
				$entity[0] = $this->resolveEntityClass($entity[0], $recursive);
				if (!$entity[0]) {
					return;
				} elseif (isset($this->definitions[$service]) && $this->definitions[$service]->getImplement()) { // @Implement::create
					return $entity[1] === 'create' ? $this->resolveServiceClass($service, $recursive) : NULL;
				}
			}

			try {
				$reflection = Nette\Utils\Callback::toReflection($entity[0] === '' ? $entity[1] : $entity);
				$refClass = $reflection instanceof \ReflectionMethod ? $reflection->getDeclaringClass() : NULL;
			} catch (\ReflectionException $e) {
			}

			if (isset($e) || ($refClass && (!$reflection->isPublic()
				|| ($refClass->isTrait() && !$reflection->isStatic())
			))) {
				$name = array_slice(array_keys($recursive), -1);
				throw new ServiceCreationException(sprintf("Factory '%s' used in service '%s' is not callable.", Nette\Utils\Callback::toString($entity), $name[0]));
			}

			return PhpReflection::getReturnType($reflection);

		} elseif ($service = $this->getServiceName($entity)) { // alias or factory
			if (Strings::contains($service, '\\')) { // @\Class
				return ltrim($service, '\\');
			}
			return $this->definitions[$service]->getImplement() ?: $this->resolveServiceClass($service, $recursive);

		} elseif (is_string($entity)) {
			$name = array_slice(array_keys($recursive), -1);
			if (!class_exists($entity)) {
				throw new ServiceCreationException("Class $entity used in service '$name[0]' not found.");
			} elseif ((new ReflectionClass($entity))->isAbstract()) {
				throw new ServiceCreationException("Class $entity used in service '$name[0]' is abstract.");
			} elseif (($rm = (new ReflectionClass($entity))->getConstructor()) !== NULL && !$rm->isPublic()) {
				$visibility = $rm->isProtected() ? 'protected' : 'private';
				throw new ServiceCreationException("Class $entity used in service '$name[0]' has $visibility constructor.");
			}
			return ltrim($entity, '\\');
		}
	}


	private function checkCase($class)
	{
		if ((class_exists($class) || interface_exists($class)) && $class !== ($name = (new ReflectionClass($class))->getName())) {
			throw new ServiceCreationException("Case mismatch on class name '$class', correct name is '$name'.");
		}
	}


	/**
	 * @param  string[]
	 * @return self
	 */
	public function addExcludedClasses(array $classes)
	{
		$this->excludedClasses = array_merge($this->excludedClasses, $classes);
		return $this;
	}


	/**
	 * Adds a file to the list of dependencies.
	 * @return self
	 * @internal
	 */
	public function addDependency($file)
	{
		$this->dependencies[$file] = TRUE;
		return $this;
	}


	/**
	 * Returns the list of dependent files.
	 * @return array
	 */
	public function getDependencies()
	{
		unset($this->dependencies[FALSE]);
		return array_keys($this->dependencies);
	}


	/********************* code generator ****************d*g**/


	/**
	 * Generates PHP classes. First class is the container.
	 * @return Nette\PhpGenerator\ClassType[]
	 */
	public function generateClasses($className = NULL, $parentName = NULL)
	{
		$this->prepareClassList();

		$this->generatedClasses = [];
		$this->className = $className ?: $this->className;
		$containerClass = $this->generatedClasses[] = new Nette\PhpGenerator\ClassType($this->className);
		$containerClass->setExtends($parentName ?: Container::class);
		$containerClass->addMethod('__construct')
			->addBody('parent::__construct(?);', [$this->parameters]);

		$definitions = $this->definitions;
		ksort($definitions);

		$meta = $containerClass->addProperty('meta')
			->setVisibility('protected')
			->setValue([Container::TYPES => $this->classes]);

		foreach ($definitions as $name => $def) {
			$meta->value[Container::SERVICES][$name] = $def->getClass() ?: NULL;
			foreach ($def->getTags() as $tag => $value) {
				$meta->value[Container::TAGS][$tag][$name] = $value;
			}
		}

		foreach ($definitions as $name => $def) {
			try {
				$name = (string) $name;
				$methodName = Container::getMethodName($name);
				if (!PhpHelpers::isIdentifier($methodName)) {
					throw new ServiceCreationException('Name contains invalid characters.');
				}
				$containerClass->addMethod($methodName)
					->addDocument('@return ' . ($def->getImplement() ?: $def->getClass()))
					->setBody($name === self::THIS_CONTAINER ? 'return $this;' : $this->generateService($name))
					->setParameters($def->getImplement() ? [] : $this->convertParameters($def->parameters));
			} catch (\Exception $e) {
				throw new ServiceCreationException("Service '$name': " . $e->getMessage(), 0, $e);
			}
		}

		$aliases = $this->aliases;
		ksort($aliases);
		$meta->value[Container::ALIASES] = $aliases;

		return $this->generatedClasses;
	}


	/**
	 * Generates body of service method.
	 * @return string
	 */
	private function generateService($name)
	{
		$this->currentService = NULL;
		$def = $this->definitions[$name];

		if ($def->isDynamic()) {
			return PhpHelpers::formatArgs('throw new Nette\\DI\\ServiceCreationException(?);',
				["Unable to create dynamic service '$name', it must be added using addService()"]
			);
		}

		$entity = $def->getFactory()->getEntity();
		$serviceRef = $this->getServiceName($entity);
		$factory = $serviceRef && !$def->getFactory()->arguments && !$def->getSetup() && $def->getImplementType() !== 'create'
			? new Statement(['@' . self::THIS_CONTAINER, 'getService'], [$serviceRef])
			: $def->getFactory();

		$code = '$service = ' . $this->formatStatement($factory) . ";\n";
		$this->currentService = $name;

		if (($class = $def->getClass()) && !$serviceRef && $class !== $entity
			&& !(is_string($entity) && preg_match('#^[\w\\\\]+\z#', $entity) && is_subclass_of($entity, $class))
		) {
			$code .= PhpHelpers::formatArgs("if (!\$service instanceof $class) {\n"
				. "\tthrow new Nette\\UnexpectedValueException(?);\n}\n",
				["Unable to create service '$name', value returned by factory is not $class type."]
			);
		}

		foreach ($def->getSetup() as $setup) {
			if (is_string($setup->getEntity()) && strpbrk($setup->getEntity(), ':@?\\') === FALSE) { // auto-prepend @self
				$setup = new Statement(['@self', $setup->getEntity()], $setup->arguments);
			}
			$code .= $this->formatStatement($setup) . ";\n";
		}
		$this->currentService = NULL;

		$code .= 'return $service;';

		if (!$def->getImplement()) {
			return $code;
		}

		$factoryClass = $this->generatedClasses[] = new Nette\PhpGenerator\ClassType;
		$factoryClass->setName(str_replace(['\\', '.'], '_', "{$this->className}_{$def->getImplement()}Impl_{$name}"))
			->addImplement($def->getImplement())
			->setFinal(TRUE);

		$factoryClass->addProperty('container')
			->setVisibility('private');

		$factoryClass->addMethod('__construct')
			->addBody('$this->container = $container;')
			->addParameter('container')
				->setTypeHint($this->className);

		$factoryClass->addMethod($def->getImplementType())
			->setParameters($this->convertParameters($def->parameters))
			->setBody(str_replace('$this', '$this->container', $code))
			->setReturnType(PHP_VERSION_ID >= 70000 ? $def->getClass() : NULL);

		return "return new {$factoryClass->getName()}(\$this);";
	}


	/**
	 * Converts parameters from ServiceDefinition to PhpGenerator.
	 * @return Nette\PhpGenerator\Parameter[]
	 */
	private function convertParameters(array $parameters)
	{
		$res = [];
		foreach ($parameters as $k => $v) {
			$tmp = explode(' ', is_int($k) ? $v : $k);
			$param = $res[] = new Nette\PhpGenerator\Parameter;
			$param->setName(end($tmp));
			if (!is_int($k)) {
				$param = $param->setOptional(TRUE)->setDefaultValue($v);
			}
			if (isset($tmp[1])) {
				$param->setTypeHint($tmp[0]);
			}
		}
		return $res;
	}


	/**
	 * Formats PHP code for class instantiating, function calling or property setting in PHP.
	 * @return string
	 * @internal
	 */
	public function formatStatement(Statement $statement)
	{
		$entity = $this->normalizeEntity($statement->getEntity());
		$arguments = $statement->arguments;

		if (is_string($entity) && Strings::contains($entity, '?')) { // PHP literal
			return $this->formatPhp($entity, $arguments);

		} elseif ($service = $this->getServiceName($entity)) { // factory calling
			$params = [];
			foreach ($this->definitions[$service]->parameters as $k => $v) {
				$params[] = preg_replace('#\w+\z#', '\$$0', (is_int($k) ? $v : $k)) . (is_int($k) ? '' : ' = ' . PhpHelpers::dump($v));
			}
			$rm = new \ReflectionFunction(create_function(implode(', ', $params), ''));
			$arguments = Helpers::autowireArguments($rm, $arguments, $this);
			return $this->formatPhp('$this->?(?*)', [Container::getMethodName($service), $arguments]);

		} elseif ($entity === 'not') { // operator
			return $this->formatPhp('!?', [$arguments[0]]);

		} elseif (is_string($entity)) { // class name
			if ($constructor = (new ReflectionClass($entity))->getConstructor()) {
				$this->addDependency((string) $constructor->getFileName());
				$arguments = Helpers::autowireArguments($constructor, $arguments, $this);
			} elseif ($arguments) {
				throw new ServiceCreationException("Unable to pass arguments, class $entity has no constructor.");
			}
			return $this->formatPhp("new $entity" . ($arguments ? '(?*)' : ''), [$arguments]);

		} elseif (!Nette\Utils\Arrays::isList($entity) || count($entity) !== 2) {
			throw new ServiceCreationException(sprintf('Expected class, method or property, %s given.', PhpHelpers::dump($entity)));

		} elseif (!preg_match('#^\$?' . PhpHelpers::PHP_IDENT . '\z#', $entity[1])) {
			throw new ServiceCreationException("Expected function, method or property name, '$entity[1]' given.");

		} elseif ($entity[0] === '') { // globalFunc
			return $this->formatPhp("$entity[1](?*)", [$arguments]);

		} elseif ($entity[0] instanceof Statement) {
			$inner = $this->formatPhp('?', [$entity[0]]);
			if (substr($inner, 0, 4) === 'new ') {
				$inner = "($inner)";
			}
			return $this->formatPhp("$inner->?(?*)", [$entity[1], $arguments]);

		} elseif (Strings::contains($entity[1], '$')) { // property setter
			Validators::assert($arguments, 'list:1', "setup arguments for '" . Nette\Utils\Callback::toString($entity) . "'");
			if ($this->getServiceName($entity[0])) {
				return $this->formatPhp('?->? = ?', [$entity[0], substr($entity[1], 1), $arguments[0]]);
			} else {
				return $this->formatPhp($entity[0] . '::$? = ?', [substr($entity[1], 1), $arguments[0]]);
			}

		} elseif ($service = $this->getServiceName($entity[0])) { // service method
			$class = $this->definitions[$service]->getImplement();
			if (!$class || !method_exists($class, $entity[1])) {
				$class = $this->definitions[$service]->getClass();
			}
			if ($class) {
				$arguments = $this->autowireArguments($class, $entity[1], $arguments);
			}
			return $this->formatPhp('?->?(?*)', [$entity[0], $entity[1], $arguments]);

		} else { // static method
			$arguments = $this->autowireArguments($entity[0], $entity[1], $arguments);
			return $this->formatPhp("$entity[0]::$entity[1](?*)", [$arguments]);
		}
	}


	/**
	 * Formats PHP statement.
	 * @return string
	 * @internal
	 */
	public function formatPhp($statement, $args)
	{
		array_walk_recursive($args, function (& $val) {
			if ($val instanceof Statement) {
				$val = self::literal($this->formatStatement($val));

			} elseif ($val === $this) {
				$val = self::literal('$this');

			} elseif ($val instanceof ServiceDefinition) {
				$val = '@' . current(array_keys($this->getDefinitions(), $val, TRUE));
			}

			if (!is_string($val)) {
				return;

			} elseif (substr($val, 0, 2) === '@@') {
				$val = substr($val, 1);

			} elseif (substr($val, 0, 1) === '@' && strlen($val) > 1) {
				$pair = explode('::', $val, 2);
				$name = $this->getServiceName($pair[0]);
				if (isset($pair[1]) && preg_match('#^[A-Z][A-Z0-9_]*\z#', $pair[1], $m)) {
					$val = $this->getDefinition($name)->getClass() . '::' . $pair[1];
				} else {
					if ($name === self::THIS_CONTAINER) {
						$val = '$this';
					} elseif ($name === $this->currentService) {
						$val = '$service';
					} else {
						$val = $this->formatStatement(new Statement(['@' . self::THIS_CONTAINER, 'getService'], [$name]));
					}
					$val .= (isset($pair[1]) ? PhpHelpers::formatArgs('->?', [$pair[1]]) : '');
				}
				$val = self::literal($val);
			}
		});
		return PhpHelpers::formatArgs($statement, $args);
	}


	/**
	 * Expands %placeholders% in strings.
	 * @return mixed
	 * @deprecated
	 */
	public function expand($value)
	{
		return Helpers::expand($value, $this->parameters);
	}


	/**
	 * @return Nette\PhpGenerator\PhpLiteral
	 */
	public static function literal($phpCode)
	{
		return new Nette\PhpGenerator\PhpLiteral($phpCode);
	}


	/** @internal */
	public function normalizeEntity($entity)
	{
		if (is_string($entity) && Strings::contains($entity, '::') && !Strings::contains($entity, '?')) { // Class::method -> [Class, method]
			$entity = explode('::', $entity);
		}

		if (is_array($entity) && $entity[0] instanceof ServiceDefinition) { // [ServiceDefinition, ...] -> [@serviceName, ...]
			$entity[0] = '@' . current(array_keys($this->definitions, $entity[0], TRUE));

		} elseif ($entity instanceof ServiceDefinition) { // ServiceDefinition -> @serviceName
			$entity = '@' . current(array_keys($this->definitions, $entity, TRUE));

		} elseif (is_array($entity) && $entity[0] === $this) { // [$this, ...] -> [@container, ...]
			$entity[0] = '@' . self::THIS_CONTAINER;
		}
		return $entity; // Class, @service, [Class, member], [@service, member], [, globalFunc], Statement
	}


	/**
	 * Converts @service or @\Class -> service name and checks its existence.
	 * @return string  of FALSE, if argument is not service name
	 * @internal
	 */
	public function getServiceName($arg)
	{
		$arg = $this->normalizeEntity($arg);
		if (!is_string($arg) || !preg_match('#^@[\w\\\\.].*\z#', $arg)) {
			return FALSE;
		}
		$service = substr($arg, 1);
		if ($service === self::THIS_SERVICE) {
			$service = $this->currentService;
		}
		if (Strings::contains($service, '\\')) {
			if ($this->classes === FALSE) { // may be disabled by prepareClassList
				return $service;
			}
			$res = $this->getByType($service);
			if (!$res) {
				throw new ServiceCreationException("Reference to missing service of type $service.");
			}
			return $res;
		}
		$service = isset($this->aliases[$service]) ? $this->aliases[$service] : $service;
		if (!isset($this->definitions[$service])) {
			throw new ServiceCreationException("Reference to missing service '$service'.");
		}
		return $service;
	}


	/**
	 * @internal
	 */
	public function invalidateClassList()
	{
		$this->classListObsolete = TRUE;
	}

}
