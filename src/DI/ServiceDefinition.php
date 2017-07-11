<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI;

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
	const
		IMPLEMENT_MODE_CREATE = 'create',
		IMPLEMENT_MODE_GET = 'get';

	use Nette\SmartObject;

	/** @var string|null  class or interface name */
	private $class;

	/** @var Statement|null */
	private $factory;

	/** @var Statement[] */
	private $setup = [];

	/** @var array */
	public $parameters = [];

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

	/** @var callable */
	private $notifier = 'pi'; // = noop


	/**
	 * @return static
	 */
	public function setClass($class, array $args = [])
	{
		($this->notifier)();
		$this->class = $class;
		if ($args) {
			$this->setFactory($class, $args);
		}
		return $this;
	}


	/**
	 * @return string|null
	 */
	public function getClass()
	{
		return $this->class;
	}


	/**
	 * @return static
	 */
	public function setFactory($factory, array $args = [])
	{
		($this->notifier)();
		$this->factory = $factory instanceof Statement ? $factory : new Statement($factory, $args);
		return $this;
	}


	/**
	 * @return Statement|null
	 */
	public function getFactory()
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
			$this->factory = new Statement($this->class);
		}
		$this->factory->arguments = $args;
		return $this;
	}


	/**
	 * @param  Statement[]
	 * @return static
	 */
	public function setSetup(array $setup)
	{
		foreach ($setup as $v) {
			if (!$v instanceof Statement) {
				throw new Nette\InvalidArgumentException('Argument must be Nette\DI\Statement[].');
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
	 * @param  bool|string|string[]
	 * @return static
	 */
	public function setAutowired($state = true)
	{
		($this->notifier)();
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
		($this->notifier)();
		$this->implement = $interface;
		return $this;
	}


	/**
	 * @return string|null
	 */
	public function getImplement()
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


	/**
	 * @return string|null
	 */
	public function getImplementMode()
	{
		return $this->implementMode;
	}


	/** @deprecated */
	public function setInject(bool $state = true)
	{
		trigger_error(__METHOD__ . "() is deprecated, use addTag('inject')", E_USER_DEPRECATED);
		return $this->addTag(Extensions\InjectExtension::TAG_INJECT, $state);
	}


	/**
	 * @internal
	 */
	public function setNotifier(callable $notifier)
	{
		$this->notifier = $notifier;
	}


	public function __clone()
	{
		$this->factory = unserialize(serialize($this->factory));
		$this->setup = unserialize(serialize($this->setup));
		$this->notifier = 'pi';
	}
}
