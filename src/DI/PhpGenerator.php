<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI;

use Nette;
use Nette\DI\Definitions\Statement;
use Nette\PhpGenerator\Helpers as PhpHelpers;
use Nette\PhpGenerator\PhpLiteral;
use Nette\Utils\Reflection;
use Nette\Utils\Strings;


/**
 * Container PHP code generator.
 */
class PhpGenerator
{
	/** @var ContainerBuilder */
	private $builder;

	/** @var string */
	private $className;

	/** @var string */
	private $currentService;


	public function __construct(ContainerBuilder $builder)
	{
		$this->builder = $builder;
	}


	/**
	 * Generates PHP classes. First class is the container.
	 */
	public function generate(string $className): Nette\PhpGenerator\ClassType
	{
		$this->builder->complete();

		$this->className = $className;
		$containerClass = new Nette\PhpGenerator\ClassType($this->className);
		$containerClass->setExtends(Container::class);
		$containerClass->addMethod('__construct')
			->addBody('$this->parameters = $params;')
			->addBody('$this->parameters += ?;', [$this->builder->parameters])
			->addParameter('params', [])
				->setTypeHint('array');

		$containerClass->addProperty('meta')
			->setVisibility('protected')
			->setValue($this->builder->exportMeta());

		$definitions = $this->builder->getDefinitions();
		ksort($definitions);

		foreach ($definitions as $name => $def) {
			try {
				$name = (string) $name;
				$methodName = Container::getMethodName($name);
				if (!PhpHelpers::isIdentifier($methodName)) {
					throw new ServiceCreationException('Name contains invalid characters.');
				}
				$containerClass->addMethod($methodName)
					->setReturnType($def->getImplement() ?: $def->getType())
					->setBody($name === ContainerBuilder::THIS_CONTAINER ? 'return $this;' : $this->generateService($name))
					->setParameters($def->getImplement() ? [] : $this->convertParameters($def->parameters));
			} catch (\Exception $e) {
				throw new ServiceCreationException("Service '$name': " . $e->getMessage(), 0, $e);
			}
		}

		return $containerClass;
	}


	/**
	 * Generates body of service method.
	 */
	private function generateService(string $name): string
	{
		$def = $this->builder->getDefinition($name);

		if ($def->isDynamic()) {
			return PhpHelpers::formatArgs('throw new Nette\\DI\\ServiceCreationException(?);',
				["Unable to create dynamic service '$name', it must be added using addService()"]
			);
		}

		$entity = $def->getFactory()->getEntity();
		$serviceRef = $this->getServiceName($entity);
		$factory = $serviceRef && !$def->getFactory()->arguments && !$def->getSetup() && $def->getImplementMode() !== $def::IMPLEMENT_MODE_CREATE
			? new Statement(['@' . ContainerBuilder::THIS_CONTAINER, 'getService'], [$serviceRef])
			: $def->getFactory();

		$this->currentService = null;
		$code = '$service = ' . $this->formatStatement($factory) . ";\n";

		if (
			$def->getSetup()
			&& ($type = $def->getType())
			&& !$serviceRef && $type !== $entity
			&& !(is_string($entity) && preg_match('#^[\w\\\\]+\z#', $entity) && is_subclass_of($entity, $type))
		) {
			$code .= PhpHelpers::formatArgs("if (!\$service instanceof $type) {\n"
				. "\tthrow new Nette\\UnexpectedValueException(?);\n}\n",
				["Unable to create service '$name', value returned by factory is not $type type."]
			);
		}

		$this->currentService = $name;
		foreach ($def->getSetup() as $setup) {
			$code .= $this->formatStatement($setup) . ";\n";
		}

		$code .= 'return $service;';

		if (!$def->getImplement()) {
			return $code;
		}

		$factoryClass = (new Nette\PhpGenerator\ClassType)
			->addImplement($def->getImplement());

		$factoryClass->addProperty('container')
			->setVisibility('private');

		$factoryClass->addMethod('__construct')
			->addBody('$this->container = $container;')
			->addParameter('container')
				->setTypeHint($this->className);

		$rm = new \ReflectionMethod($def->getImplement(), $def->getImplementMode());

		$factoryClass->addMethod($def->getImplementMode())
			->setParameters($this->convertParameters($def->parameters))
			->setBody(str_replace('$this', '$this->container', $code))
			->setReturnType(Reflection::getReturnType($rm) ?: $def->getType());

		return 'return new class ($this) ' . $factoryClass . ';';
	}


	/**
	 * Formats PHP code for class instantiating, function calling or property setting in PHP.
	 */
	private function formatStatement(Statement $statement): string
	{
		$entity = $statement->getEntity();
		$arguments = $statement->arguments;

		if (is_string($entity) && Strings::contains($entity, '?')) { // PHP literal
			return $this->formatPhp($entity, $arguments);

		} elseif ($service = $this->getServiceName($entity)) { // factory calling
			return $this->formatPhp('$this->?(...?)', [Container::getMethodName($service), $arguments]);

		} elseif ($entity === 'not') { // operator
			return $this->formatPhp('!?', [$arguments[0]]);

		} elseif (is_string($entity)) { // class name
			return $this->formatPhp("new $entity" . ($arguments ? '(...?)' : ''), $arguments ? [$arguments] : []);

		} elseif ($entity[0] === '') { // globalFunc
			return $this->formatPhp("$entity[1](...?)", [$arguments]);

		} elseif ($entity[0] instanceof Statement) {
			$inner = $this->formatPhp('?', [$entity[0]]);
			if (substr($inner, 0, 4) === 'new ') {
				$inner = "($inner)";
			}
			return $this->formatPhp("$inner->?(...?)", [$entity[1], $arguments]);

		} elseif ($entity[1][0] === '$') { // property getter, setter or appender
			$name = substr($entity[1], 1);
			if ($append = (substr($name, -2) === '[]')) {
				$name = substr($name, 0, -2);
			}
			if ($this->getServiceName($entity[0])) {
				$prop = $this->formatPhp('?->?', [$entity[0], $name]);
			} else {
				$prop = $this->formatPhp($entity[0] . '::$?', [$name]);
			}
			return $arguments
				? $this->formatPhp($prop . ($append ? '[]' : '') . ' = ?', [$arguments[0]])
				: $prop;

		} elseif ($service = $this->getServiceName($entity[0])) { // service method
			return $this->formatPhp('?->?(...?)', [$entity[0], $entity[1], $arguments]);

		} else { // static method
			return $this->formatPhp("$entity[0]::$entity[1](...?)", [$arguments]);
		}
	}


	/**
	 * Converts @service or @\Class -> service name and checks its existence.
	 */
	private function getServiceName($arg): ?string
	{
		if (!is_string($arg) || !preg_match('#^@[\w\\\\.][^:]*\z#', $arg)) {
			return null;
		}
		$service = substr($arg, 1);
		if (Strings::contains($service, '\\')) {
			$res = $this->builder->getByType($service);
			if (!$res) {
				throw new ServiceCreationException("Reference to missing service of type $service.");
			}
			return $res;
		}
		if (!$this->builder->hasDefinition($service)) {
			throw new ServiceCreationException("Reference to missing service '$service'.");
		}
		return $service;
	}


	/**
	 * Formats PHP statement.
	 * @internal
	 */
	public function formatPhp(string $statement, array $args): string
	{
		array_walk_recursive($args, function (&$val): void {
			if ($val instanceof Statement) {
				$val = new PhpLiteral($this->formatStatement($val));

			} elseif (is_string($val) && substr($val, 0, 2) === '@@') { // escaped text @@
				$val = substr($val, 1);

			} elseif (is_string($val) && substr($val, 0, 1) === '@' && strlen($val) > 1) { // service reference
				$name = substr($val, 1);
				if ($name === ContainerBuilder::THIS_CONTAINER) {
					$val = new PhpLiteral('$this');
				} elseif ($name === $this->currentService) {
					$val = new PhpLiteral('$service');
				} else {
					$val = new PhpLiteral($this->formatStatement(new Statement(['@' . ContainerBuilder::THIS_CONTAINER, 'getService'], [$name])));
				}
			}
		});
		return PhpHelpers::formatArgs($statement, $args);
	}


	/**
	 * Converts parameters from ServiceDefinition to PhpGenerator.
	 * @return Nette\PhpGenerator\Parameter[]
	 */
	private function convertParameters(array $parameters): array
	{
		$res = [];
		foreach ($parameters as $k => $v) {
			$tmp = explode(' ', is_int($k) ? $v : $k);
			$param = $res[] = new Nette\PhpGenerator\Parameter(end($tmp));
			if (!is_int($k)) {
				$param->setDefaultValue($v);
			}
			if (isset($tmp[1])) {
				$param->setTypeHint($tmp[0]);
			}
		}
		return $res;
	}
}
