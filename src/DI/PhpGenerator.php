<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI;

use Nette;
use Nette\DI\Definitions\Reference;
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
	use Nette\SmartObject;

	/** @var ContainerBuilder */
	private $builder;

	/** @var string */
	private $className;


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

		$definitions = $this->builder->getDefinitions();
		ksort($definitions);

		$meta = $containerClass->addProperty('meta')
			->setVisibility('protected')
			->setValue($this->builder->exportMeta());

		foreach ($definitions as $name => $def) {
			$meta->value[Container::SERVICES][$name] = $def->getImplement() ?: $def->getType() ?: null;
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
					->setReturnType($def->getImplement() ?: $def->getType())
					->setBody($name === ContainerBuilder::THIS_CONTAINER ? 'return $this;' : $this->generateService($name))
					->setParameters($def->getImplement() ? [] : $this->convertParameters($def->parameters));
			} catch (\Exception $e) {
				throw new ServiceCreationException("Service '$name': " . $e->getMessage(), 0, $e);
			}
		}

		$aliases = $this->builder->getAliases();
		ksort($aliases);
		$meta->value[Container::ALIASES] = $aliases;

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
		$code = '$service = ' . $this->formatStatement($def->getFactory()) . ";\n";

		if (
			$def->getSetup()
			&& ($type = $def->getType())
			&& !$entity instanceof Reference && $type !== $entity
			&& !(is_string($entity) && preg_match('#^[\w\\\\]+\z#', $entity) && is_subclass_of($entity, $type))
		) {
			$code .= PhpHelpers::formatArgs("if (!\$service instanceof $type) {\n"
				. "\tthrow new Nette\\UnexpectedValueException(?);\n}\n",
				["Unable to create service '$name', value returned by factory is not $type type."]
			);
		}

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

		switch (true) {
			case is_string($entity) && Strings::contains($entity, '?'): // PHP literal
				return $this->formatPhp($entity, $arguments);

			case $entity === 'not':
				return $this->formatPhp('!?', [$arguments[0]]);

			case is_string($entity): // create class
				return $this->formatPhp("new $entity" . ($arguments ? '(...?)' : ''), $arguments ? [$arguments] : []);

			case $entity instanceof Reference:
				return $this->formatPhp('$this->?(...?)', [Container::getMethodName($entity->getValue()), $arguments]);

			case is_array($entity):
				switch (true) {
					case $entity[1][0] === '$': // property getter, setter or appender
						$name = substr($entity[1], 1);
						if ($append = (substr($name, -2) === '[]')) {
							$name = substr($name, 0, -2);
						}
						if ($entity[0] instanceof Reference) {
							$prop = $this->formatPhp('?->?', [$entity[0], $name]);
						} else {
							$prop = $this->formatPhp($entity[0] . '::$?', [$name]);
						}
						return $arguments
							? $this->formatPhp($prop . ($append ? '[]' : '') . ' = ?', [$arguments[0]])
							: $prop;

					case $entity[0] instanceof Statement:
						$inner = $this->formatPhp('?', [$entity[0]]);
						if (substr($inner, 0, 4) === 'new ') {
							$inner = "($inner)";
						}
						return $this->formatPhp("$inner->?(...?)", [$entity[1], $arguments]);

					case $entity[0] instanceof Reference:
						return $this->formatPhp('?->?(...?)', [$entity[0], $entity[1], $arguments]);

					case $entity[0] === '': // function call
						return $this->formatPhp("$entity[1](...?)", [$arguments]);

					case is_string($entity[0]): // static method call
						return $this->formatPhp("$entity[0]::$entity[1](...?)", [$arguments]);
				}
		}

		throw Nette\InvalidStateException;
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

			} elseif ($val instanceof Reference) {
				$name = $val->getValue();
				if ($val->isSelf()) {
					$val = new PhpLiteral('$service');
				} elseif ($name === ContainerBuilder::THIS_CONTAINER) {
					$val = new PhpLiteral('$this');
				} else {
					$val = ContainerBuilder::literal('$this->getService(?)', [$name]);
				}
			}
		});
		return PhpHelpers::formatArgs($statement, $args);
	}


	/**
	 * Converts parameters from Definition to PhpGenerator.
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
