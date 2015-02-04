<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\DI;

use Nette,
	Nette\Utils\Validators,
	Nette\Utils\Strings,
	Nette\Reflection,
	Nette\PhpGenerator\Helpers as PhpHelpers;


/**
 * Basic container builder.
 *
 * @author     David Grudl
 */
class ContainerBuilder extends Nette\Object
{
	const THIS_SERVICE = 'self',
		THIS_CONTAINER = 'container';

	/** @var array */
	public $parameters = array();

	/** @var ServiceDefinition[] */
	private $definitions = array();

	/** @var array of alias => service */
	private $aliases = array();

	/** @var array for auto-wiring */
	private $classes;

	/** @var string[] of classes excluded from auto-wiring */
	private $excludedClasses = array();

	/** @var array of file names */
	private $dependencies = array();

	/** @var Nette\PhpGenerator\ClassType[] */
	private $generatedClasses = array();

	/** @var string */
	/*private in 5.4*/public $currentService;


	/**
	 * Adds new service definition.
	 * @param  string
	 * @return ServiceDefinition
	 */
	public function addDefinition($name, ServiceDefinition $definition = NULL)
	{
		if (!is_string($name) || !$name) { // builder is not ready for falsy names such as '0'
			throw new Nette\InvalidArgumentException(sprintf('Service name must be a non-empty string, %s given.', gettype($name)));
		}
		$name = isset($this->aliases[$name]) ? $this->aliases[$name] : $name;
		if (isset($this->definitions[$name])) {
			throw new Nette\InvalidStateException("Service '$name' has already been added.");
		}
		return $this->definitions[$name] = $definition ?: new ServiceDefinition;
	}


	/**
	 * Removes the specified service definition.
	 * @param  string
	 * @return void
	 */
	public function removeDefinition($name)
	{
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
	 * @param string
	 * @param string
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
	 * Gets all service aliases.
	 * @return array
	 */
	public function getAliases()
	{
		return $this->aliases;
	}


	/********************* class resolving ****************d*g**/


	/**
	 * Resolves service name by type.
	 * @param  string  class or interface
	 * @return string  service name or NULL
	 * @throws ServiceCreationException
	 */
	public function getByType($class)
	{
		if ($this->currentService !== NULL && Reflection\ClassType::from($this->definitions[$this->currentService]->getClass())->is($class)) {
			return $this->currentService;
		}

		$lower = ltrim(strtolower($class), '\\');
		if (!isset($this->classes[$lower][TRUE])) {
			return;

		} elseif (count($this->classes[$lower][TRUE]) === 1) {
			return $this->classes[$lower][TRUE][0];

		} else {
			throw new ServiceCreationException("Multiple services of type $class found: " . implode(', ', $this->classes[$lower][TRUE]));
		}
	}


	/**
	 * Gets the service names of the specified type.
	 * @param string
	 * @return string[]
	 */
	public function findByType($class, $autowired = TRUE)
	{
		$class = ltrim(strtolower($class), '\\');
		return array_merge(
			isset($this->classes[$class][TRUE]) ? $this->classes[$class][TRUE] : array(),
			!$autowired && isset($this->classes[$class][FALSE]) ? $this->classes[$class][FALSE] : array()
		);
	}


	/**
	 * Gets the service objects of the specified tag.
	 * @param  string
	 * @return array of [service name => tag attributes]
	 */
	public function findByTag($tag)
	{
		$found = array();
		foreach ($this->definitions as $name => $def) {
			if (($tmp = $def->getTag($tag)) !== NULL) {
				$found[$name] = $tmp;
			}
		}
		return $found;
	}


	/**
	 * Creates a list of arguments using autowiring.
	 * @return array
	 */
	public function autowireArguments($class, $method, array $arguments)
	{
		$rc = Reflection\ClassType::from($class);
		if (!$rc->hasMethod($method)) {
			if (!Nette\Utils\Arrays::isList($arguments)) {
				throw new ServiceCreationException("Unable to pass specified arguments to $class::$method().");
			}
			return $arguments;
		}

		$rm = $rc->getMethod($method);
		if (!$rm->isPublic()) {
			throw new ServiceCreationException("$rm is not callable.");
		}
		$this->addDependency($rm->getFileName());
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
		$this->addDefinition(self::THIS_CONTAINER)->setClass('Nette\DI\Container');

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
				$def->setFactory($def->getClass(), ($factory = $def->getFactory()) ? $factory->arguments : array());
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
		$excludedClasses = array();
		foreach ($this->excludedClasses as $class) {
			$excludedClasses += array_change_key_case(class_parents($class) + class_implements($class) + array($class => $class));
		}

		$this->classes = array();
		foreach ($this->definitions as $name => $def) {
			if ($class = $def->getImplement() ?: $def->getClass()) {
				foreach (class_parents($class) + class_implements($class) + array($class) as $parent) {
					$parent = strtolower($parent);
					$this->classes[$parent][$def->isAutowired() && empty($excludedClasses[$parent])][] = (string) $name;
				}
			}
		}

		foreach ($this->classes as $class => $foo) {
			$this->addDependency(Reflection\ClassType::from($class)->getFileName());
		}
	}


	private function resolveImplement(ServiceDefinition $def, $name)
	{
		$implement = $def->getImplement();
		if (!interface_exists($implement)) {
			throw new ServiceCreationException("Interface $implement used in service '$name' not found.");
		}
		$rc = Reflection\ClassType::from($implement);
		$method = $rc->hasMethod('create') ? $rc->getMethod('create') : ($rc->hasMethod('get') ? $rc->getMethod('get') : NULL);
		if (count($rc->getMethods()) !== 1 || !$method || $method->isStatic()) {
			throw new ServiceCreationException("Interface $implement used in service '$name' must have just one non-static method create() or get().");
		}
		$def->setImplement($rc->getName());
		$def->setImplementType($rc->hasMethod('create') ? 'create' : 'get');

		if (!$def->getClass() && !$def->getEntity()) {
			$returnType = $method->getAnnotation('return');
			if (!$returnType) {
				throw new ServiceCreationException("Method $method used in service '$name' has no @return annotation.");
			}

			$returnType = Reflection\AnnotationsParser::expandClassName(preg_replace('#[|\s].*#', '', $returnType), $rc);
			if (!class_exists($returnType)) {
				throw new ServiceCreationException("Please check a @return annotation of the $method method used in service '$name'. Class '$returnType' cannot be found.");
			}
			$def->setClass($returnType);
		}

		if ($method->getName() === 'get') {
			if ($method->getParameters()) {
				throw new ServiceCreationException("Method $method used in service '$name' must have no arguments.");
			}
			if (!$def->getEntity()) {
				$def->setFactory('@\\' . ltrim($def->getClass(), '\\'));
			} elseif (!$this->getServiceName($def->getFactory()->getEntity())) {
				throw new ServiceCreationException("Invalid factory in service '$name' definition.");
			}
		}

		if (!$def->parameters) {
			$ctorParams = array();
			if (!$def->getEntity()) {
				$def->setFactory($def->getClass(), $def->getFactory() ? $def->getFactory()->arguments : array());
			}
			if (($class = $this->resolveEntityClass($def->getFactory(), array($name => 1)))
				&& ($ctor = Reflection\ClassType::from($class)->getConstructor())
			) {
				foreach ($ctor->getParameters() as $param) {
					$ctorParams[$param->getName()] = $param;
				}
			}

			foreach ($method->getParameters() as $param) {
				if (isset($ctorParams[$param->getName()])) {
					$arg = $ctorParams[$param->getName()];
					if ($param->getClassName() !== $arg->getClassName() || $param->isArray() !== $arg->isArray()) {
						throw new ServiceCreationException("Type hint for $arg doesn't match type hint for $param");
					}
					$def->getFactory()->arguments[$arg->getPosition()] = ContainerBuilder::literal('$' . $arg->getName());
				}
				$paramDef = ($param->isArray() ? 'array' : $param->getClassName()) . ' ' . $param->getName();
				if ($param->isOptional()) {
					$def->parameters[$paramDef] = $param->getDefaultValue();
				} else {
					$def->parameters[] = $paramDef;
				}
			}
		}
	}


	/** @return string|NULL */
	private function resolveServiceClass($name, $recursive = array())
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
				throw new ServiceCreationException("Class or interface $class used in service '$name' not found.");
			}
			$def->setClass(Reflection\ClassType::from($class)->getName());

		} elseif ($def->isAutowired()) {
			trigger_error("Type of service '$name' is unknown.", E_USER_NOTICE);
		}
		return $class;
	}


	/** @return string|NULL */
	private function resolveEntityClass($entity, $recursive = array())
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
				|| (PHP_VERSION_ID >= 50400 && $refClass->isTrait() && !$reflection->isStatic())
			))) {
				$name = array_slice(array_keys($recursive), -1);
				throw new ServiceCreationException(sprintf("Factory '%s' used in service '%s' is not callable.", Nette\Utils\Callback::toString($entity), $name[0]));
			}

			$class = preg_replace('#[|\s].*#', '', $reflection->getAnnotation('return'));
			if ($class && $refClass) {
				$class = Reflection\AnnotationsParser::expandClassName($class, $refClass);
			}
			return $class;

		} elseif ($service = $this->getServiceName($entity)) { // alias or factory
			if (Strings::contains($service, '\\')) { // @\Class
				return $service;
			}
			return $this->definitions[$service]->getImplement() ?: $this->resolveServiceClass($service, $recursive);

		} elseif (is_string($entity)) {
			if (!class_exists($entity) || !Reflection\ClassType::from($entity)->isInstantiable()) {
				$name = array_slice(array_keys($recursive), -1);
				throw new ServiceCreationException("Class $entity used in service '$name[0]' not found or is not instantiable.");
			}
			return $entity;
		}
	}


	/**
	 * @param string[]
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
	public function generateClasses($className = 'Container', $parentName = NULL)
	{
		$this->prepareClassList();

		$this->generatedClasses = array();
		$containerClass = $this->generatedClasses[] = new Nette\PhpGenerator\ClassType($className);
		$containerClass->setExtends($parentName ?: 'Nette\DI\Container');
		$containerClass->addMethod('__construct')
			->addBody('parent::__construct(?);', array($this->parameters));

		$definitions = $this->definitions;
		ksort($definitions);

		$meta = $containerClass->addProperty('meta', array())
			->setVisibility('protected')
			->setValue(array(Container::TYPES => $this->classes));

		foreach ($definitions as $name => $def) {
			if ($def->getClass()) {
				$meta->value[Container::SERVICES][$name] = $def->getClass();
			}
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
					->setParameters($def->getImplement() ? array() : $this->convertParameters($def->parameters));
			} catch (\Exception $e) {
				throw new ServiceCreationException("Service '$name': " . $e->getMessage(), NULL, $e);
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
				array("Unable to create dynamic service '$name', it must be added using addService()")
			);
		}

		$entity = $def->getFactory()->getEntity();
		$serviceRef = $this->getServiceName($entity);
		$factory = $serviceRef && !$def->getFactory()->arguments && !$def->getSetup() && $def->getImplementType() !== 'create'
			? new Statement(array('@' . ContainerBuilder::THIS_CONTAINER, 'getService'), array($serviceRef))
			: $def->getFactory();

		$code = '$service = ' . $this->formatStatement($factory) . ";\n";
		$this->currentService = $name;

		if (($class = $def->getClass()) && !$serviceRef && $class !== $entity
			&& !(is_string($entity) && preg_match('#^[\w\\\\]+\z#', $entity) && is_subclass_of($entity, $class))
		) {
			$code .= PhpHelpers::formatArgs("if (!\$service instanceof $class) {\n"
				. "\tthrow new Nette\\UnexpectedValueException(?);\n}\n",
				array("Unable to create service '$name', value returned by factory is not $class type.")
			);
		}

		foreach ($def->getSetup() as $setup) {
			if (is_string($setup->getEntity()) && strpbrk($setup->getEntity(), ':@?\\') === FALSE) { // auto-prepend @self
				$setup->setEntity(array('@self', $setup->getEntity()));
			}
			$code .= $this->formatStatement($setup) . ";\n";
		}

		$code .= 'return $service;';

		if (!$def->getImplement()) {
			return $code;
		}

		$factoryClass = $this->generatedClasses[] = new Nette\PhpGenerator\ClassType;
		$factoryClass->setName(str_replace(array('\\', '.'), '_', "{$this->generatedClasses[0]->getName()}_{$def->getImplement()}Impl_{$name}"))
			->addImplement($def->getImplement())
			->setFinal(TRUE);

		$factoryClass->addProperty('container')
			->setVisibility('private');

		$factoryClass->addMethod('__construct')
			->addBody('$this->container = $container;')
			->addParameter('container')
				->setTypeHint('Nette\DI\Container');

		$factoryClass->addMethod($def->getImplementType())
			->setParameters($this->convertParameters($def->parameters))
			->setBody(str_replace('$this', '$this->container', $code));

		return "return new {$factoryClass->getName()}(\$this);";
	}


	/**
	 * Converts parameters from ServiceDefinition to PhpGenerator.
	 * @return Nette\PhpGenerator\Parameter[]
	 */
	private function convertParameters(array $parameters)
	{
		$res = array();
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
			$params = array();
			foreach ($this->definitions[$service]->parameters as $k => $v) {
				$params[] = preg_replace('#\w+\z#', '\$$0', (is_int($k) ? $v : $k)) . (is_int($k) ? '' : ' = ' . PhpHelpers::dump($v));
			}
			$rm = new Reflection\GlobalFunction(create_function(implode(', ', $params), ''));
			$arguments = Helpers::autowireArguments($rm, $arguments, $this);
			return $this->formatPhp('$this->?(?*)', array(Container::getMethodName($service), $arguments));

		} elseif ($entity === 'not') { // operator
			return $this->formatPhp('!?', array($arguments[0]));

		} elseif (is_string($entity)) { // class name
			if ($constructor = Reflection\ClassType::from($entity)->getConstructor()) {
				$this->addDependency($constructor->getFileName());
				$arguments = Helpers::autowireArguments($constructor, $arguments, $this);
			} elseif ($arguments) {
				throw new ServiceCreationException("Unable to pass arguments, class $entity has no constructor.");
			}
			return $this->formatPhp("new $entity" . ($arguments ? '(?*)' : ''), array($arguments));

		} elseif (!Nette\Utils\Arrays::isList($entity) || count($entity) !== 2) {
			throw new ServiceCreationException(sprintf('Expected class, method or property, %s given.', PhpHelpers::dump($entity)));

		} elseif (!preg_match('#^\$?' . PhpHelpers::PHP_IDENT . '\z#', $entity[1])) {
			throw new ServiceCreationException("Expected function, method or property name, '$entity[1]' given.");

		} elseif ($entity[0] === '') { // globalFunc
			return $this->formatPhp("$entity[1](?*)", array($arguments));

		} elseif ($entity[0] instanceof Statement) {
			$inner = $this->formatPhp('?', array($entity[0]));
			if (substr($inner, 0, 4) === 'new ') {
				$inner = PHP_VERSION_ID < 50400 ? "current(array($inner))" : "($inner)";
			}
			return $this->formatPhp("$inner->?(?*)", array($entity[1], $arguments));

		} elseif (Strings::contains($entity[1], '$')) { // property setter
			Validators::assert($arguments, 'list:1', "setup arguments for '" . Nette\Utils\Callback::toString($entity) . "'");
			if ($this->getServiceName($entity[0])) {
				return $this->formatPhp('?->? = ?', array($entity[0], substr($entity[1], 1), $arguments[0]));
			} else {
				return $this->formatPhp($entity[0] . '::$? = ?', array(substr($entity[1], 1), $arguments[0]));
			}

		} elseif ($service = $this->getServiceName($entity[0])) { // service method
			$class = $this->definitions[$service]->getImplement();
			if (!$class || !method_exists($class, $entity[1])) {
				$class = $this->definitions[$service]->getClass();
			}
			if ($class) {
				$arguments = $this->autowireArguments($class, $entity[1], $arguments);
			}
			return $this->formatPhp('?->?(?*)', array($entity[0], $entity[1], $arguments));

		} else { // static method
			$arguments = $this->autowireArguments($entity[0], $entity[1], $arguments);
			return $this->formatPhp("$entity[0]::$entity[1](?*)", array($arguments));
		}
	}


	/**
	 * Formats PHP statement.
	 * @return string
	 * @internal
	 */
	public function formatPhp($statement, $args)
	{
		$that = $this;
		array_walk_recursive($args, function(& $val) use ($that) {
			if ($val instanceof Statement) {
				$val = ContainerBuilder::literal($that->formatStatement($val));

			} elseif ($val === $that) {
				$val = ContainerBuilder::literal('$this');

			} elseif ($val instanceof ServiceDefinition) {
				$val = '@' . current(array_keys($that->getDefinitions(), $val, TRUE));
			}

			if (!is_string($val)) {
				return;

			} elseif (substr($val, 0, 2) === '@@') {
				$val = substr($val, 1);

			} elseif (substr($val, 0, 1) === '@') {
				$pair = explode('::', $val, 2);
				$name = $that->getServiceName($pair[0]);
				if (isset($pair[1]) && preg_match('#^[A-Z][A-Z0-9_]*\z#', $pair[1], $m)) {
					$val = $that->getDefinition($name)->getClass() . '::' . $pair[1];
				} else {
					if ($name === ContainerBuilder::THIS_CONTAINER) {
						$val = '$this';
					} elseif ($name === $that->currentService) {
						$val = '$service';
					} else {
						$val = $that->formatStatement(new Statement(array('@' . ContainerBuilder::THIS_CONTAINER, 'getService'), array($name)));
					}
					$val .= (isset($pair[1]) ? PhpHelpers::formatArgs('->?', array($pair[1])) : '');
				}
				$val = ContainerBuilder::literal($val);
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
			$entity[0] = '@' . ContainerBuilder::THIS_CONTAINER;
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

}
