<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Definitions;

use Nette;
use Nette\PhpGenerator\Helpers as PhpHelpers;
use Nette\Utils\Reflection;


/**
 * Definition of standard service.
 *
 * @property string|null $class
 * @property Statement|null $factory
 * @property Statement[] $setup
 */
final class ServiceDefinition extends Definition
{
	public const
		IMPLEMENT_MODE_CREATE = 'create',
		IMPLEMENT_MODE_GET = 'get';

	/** @var array */
	public $parameters = [];

	/** @var Statement|null */
	private $factory;

	/** @var Statement[] */
	private $setup = [];

	/** @var bool */
	private $dynamic = false;

	/** @var string|null  interface name */
	private $implement;

	/** @var string|null  create | get */
	private $implementMode;


	/**
	 * @deprecated Use setType()
	 */
	public function setClass(?string $type)
	{
		$this->setType($type);
		if (func_num_args() > 1) {
			trigger_error(__METHOD__ . '() second parameter $args is deprecated, use setFactory()', E_USER_DEPRECATED);
			if ($args = func_get_arg(1)) {
				$this->setFactory($type, $args);
			}
		}
		return $this;
	}


	/**
	 * @return static
	 */
	public function setType(?string $type)
	{
		return parent::setType($type);
	}


	/**
	 * @param  string|array|Definition|Reference|Statement  $factory
	 * @return static
	 */
	public function setFactory($factory, array $args = [])
	{
		$this->factory = $factory instanceof Statement ? $factory : new Statement($factory, $args);
		return $this;
	}


	public function getFactory(): ?Statement
	{
		return $this->factory;
	}


	/**
	 * @return string|array|Definition|null
	 */
	public function getEntity()
	{
		return $this->factory ? $this->factory->getEntity() : null;
	}


	/**
	 * @return static
	 */
	public function setArguments(array $args = [])
	{
		if (!$this->factory) {
			$this->factory = new Statement($this->getType());
		}
		$this->factory->arguments = $args;
		return $this;
	}


	/**
	 * @param  Statement[]  $setup
	 * @return static
	 */
	public function setSetup(array $setup)
	{
		foreach ($setup as $v) {
			if (!$v instanceof Statement) {
				throw new Nette\InvalidArgumentException('Argument must be Nette\DI\Definitions\Statement[].');
			}
		}
		$this->setup = $setup;
		return $this;
	}


	/**
	 * @return Statement[]
	 */
	public function getSetup(): array
	{
		return $this->setup;
	}


	/**
	 * @param  string|array|Definition|Reference|Statement  $entity
	 * @return static
	 */
	public function addSetup($entity, array $args = [])
	{
		$this->setup[] = $entity instanceof Statement ? $entity : new Statement($entity, $args);
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


	/**
	 * @return static
	 */
	public function setDynamic(bool $state = true)
	{
		$this->dynamic = $state;
		return $this;
	}


	public function isDynamic(): bool
	{
		return $this->dynamic;
	}


	/**
	 * @return static
	 */
	public function setImplement(string $interface)
	{
		if ($this->implement !== $interface) {
			$this->setType($this->getType()); // calls notifier
		}
		if ($interface === null) {
			$this->implement = null;
		} elseif (!interface_exists($interface)) {
			throw new Nette\InvalidArgumentException("Service '{$this->getName()}': Interface '$interface' not found.");
		} else {
			$this->implement = Nette\DI\Helpers::normalizeClass($interface);
		}
		return $this;
	}


	public function getImplement(): ?string
	{
		return $this->implement;
	}


	/**
	 * @return static
	 */
	public function setImplementMode(string $mode)
	{
		if (!in_array($mode, [self::IMPLEMENT_MODE_CREATE, self::IMPLEMENT_MODE_GET], true)) {
			throw new Nette\InvalidArgumentException('Argument must be get|create.');
		}
		$this->implementMode = $mode;
		return $this;
	}


	public function getImplementMode(): ?string
	{
		return $this->implementMode;
	}


	/** @deprecated */
	public function setInject(bool $state = true)
	{
		trigger_error(__METHOD__ . "() is deprecated, use addTag('inject')", E_USER_DEPRECATED);
		return $this->addTag(Nette\DI\Extensions\InjectExtension::TAG_INJECT, $state);
	}


	public function generateMethod(Nette\PhpGenerator\Method $method, Nette\DI\PhpGenerator $generator): void
	{
		if ($this->isDynamic()) {
			$method->setBody('throw new Nette\\DI\\ServiceCreationException(?);',
				["Unable to create dynamic service '{$this->getName()}', it must be added using addService()"]
			);
			return;
		}

		$method->setParameters($this->getImplement() ? [] : $generator->convertParameters($this->parameters));

		$entity = $this->getFactory()->getEntity();
		$code = '$service = ' . $generator->formatStatement($this->getFactory()) . ";\n";

		if (
			$this->getSetup()
			&& ($type = $this->getType())
			&& !$entity instanceof Reference && $type !== $entity
			&& !(is_string($entity) && preg_match('#^[\w\\\\]+\z#', $entity) && is_subclass_of($entity, $type))
		) {
			$code .= PhpHelpers::formatArgs("if (!\$service instanceof $type) {\n"
				. "\tthrow new Nette\\UnexpectedValueException(?);\n}\n",
				["Unable to create service '{$this->getName()}', value returned by factory is not $type type."]
			);
		}

		foreach ($this->getSetup() as $setup) {
			$code .= $generator->formatStatement($setup) . ";\n";
		}

		$code .= 'return $service;';

		if (!$this->getImplement()) {
			$method->setBody($code);
			return;
		}

		$factoryClass = (new Nette\PhpGenerator\ClassType)
			->addImplement($this->getImplement());

		$factoryClass->addProperty('container')
			->setVisibility('private');

		$factoryClass->addMethod('__construct')
			->addBody('$this->container = $container;')
			->addParameter('container')
				->setTypeHint($generator->getClassName());

		$rm = new \ReflectionMethod($this->getImplement(), $this->getImplementMode());

		$factoryClass->addMethod($this->getImplementMode())
			->setParameters($generator->convertParameters($this->parameters))
			->setBody(str_replace('$this', '$this->container', $code))
			->setReturnType(Reflection::getReturnType($rm) ?: $this->getType());

		$method->setBody('return new class ($this) ' . $factoryClass . ';');
	}


	public function __clone()
	{
		parent::__clone();
		$this->factory = unserialize(serialize($this->factory));
		$this->setup = unserialize(serialize($this->setup));
	}
}
