<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Definitions;

use Nette;


/**
 * Definition used by ContainerBuilder.
 *
 * @property string|null $class
 * @property Statement|null $factory
 * @property Statement[] $setup
 */
final class ServiceDefinition
{
	use Nette\SmartObject;

	public const
		IMPLEMENT_MODE_CREATE = 'create',
		IMPLEMENT_MODE_GET = 'get';

	/** @var array */
	public $parameters = [];

	/** @var string|null */
	private $name;

	/** @var string|null  class or interface name */
	private $type;

	/** @var Statement|null */
	private $factory;

	/** @var Statement[] */
	private $setup = [];

	/** @var array */
	private $tags = [];

	/** @var bool|string[] */
	private $autowired = true;

	/** @var bool */
	private $dynamic = false;

	/** @var string|null  interface name */
	private $implement;

	/** @var string|null  create | get */
	private $implementMode;

	/** @var callable|null */
	private $notifier;


	/**
	 * @return static
	 */
	public function setName(string $name)
	{
		if ($this->name) {
			throw new Nette\InvalidStateException('Name already has been set.');
		}
		$this->name = $name;
		return $this;
	}


	public function getName(): ?string
	{
		return $this->name;
	}


	/**
	 * @return static
	 * @deprecated Use setType() instead.
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
	 * @deprecated Use getType() instead.
	 */
	public function getClass(): ?string
	{
		return $this->getType();
	}


	/**
	 * @return static
	 */
	public function setType(?string $type)
	{
		if ($this->notifier && $this->type !== $type) {
			($this->notifier)();
		}
		if ($type === null) {
			$this->type = null;
		} elseif (!class_exists($type) && !interface_exists($type)) {
			throw new Nette\InvalidArgumentException("Service '$this->name': Class or interface '$type' not found.");
		} else {
			$this->type = Nette\DI\Helpers::normalizeClass($type);
		}
		return $this;
	}


	public function getType(): ?string
	{
		return $this->type;
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
	 * @return string|array|ServiceDefinition|null
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
			$this->factory = new Statement($this->type);
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
	public function setTags(array $tags)
	{
		$this->tags = $tags;
		return $this;
	}


	public function getTags(): array
	{
		return $this->tags;
	}


	/**
	 * @return static
	 */
	public function addTag(string $tag, $attr = true)
	{
		$this->tags[$tag] = $attr;
		return $this;
	}


	/**
	 * @return mixed
	 */
	public function getTag(string $tag)
	{
		return $this->tags[$tag] ?? null;
	}


	/**
	 * @param  bool|string|string[]  $state
	 * @return static
	 */
	public function setAutowired($state = true)
	{
		if ($this->notifier && $this->autowired !== $state) {
			($this->notifier)();
		}
		$this->autowired = is_string($state) || is_array($state) ? (array) $state : (bool) $state;
		return $this;
	}


	/**
	 * @return bool|string[]
	 */
	public function isAutowired()
	{
		return $this->autowired;
	}


	/**
	 * @return bool|string[]
	 */
	public function getAutowired()
	{
		return $this->autowired;
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
		if ($this->notifier && $this->implement !== $interface) {
			($this->notifier)();
		}
		if ($interface === null) {
			$this->implement = null;
		} elseif (!interface_exists($interface)) {
			throw new Nette\InvalidArgumentException("Service '$this->name': Interface '$interface' not found.");
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


	/**
	 * @internal
	 */
	public function setNotifier(?callable $notifier): void
	{
		$this->notifier = $notifier;
	}


	public function __clone()
	{
		$this->factory = unserialize(serialize($this->factory));
		$this->setup = unserialize(serialize($this->setup));
		$this->notifier = null;
		$this->name = null;
	}
}
