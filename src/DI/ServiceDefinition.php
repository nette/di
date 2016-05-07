<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI;

use Nette;


/**
 * Definition used by ContainerBuilder.
 *
 * @property string|NULL $class
 * @property Statement|NULL $factory
 * @property Statement[] $setup
 */
class ServiceDefinition
{
	use Nette\SmartObject;

	/** @var string|NULL  class or interface name */
	private $class;

	/** @var Statement|NULL */
	private $factory;

	/** @var Statement[] */
	private $setup = [];

	/** @var array */
	public $parameters = [];

	/** @var array */
	private $tags = [];

	/** @var bool */
	private $autowired = TRUE;

	/** @var bool */
	private $dynamic = FALSE;

	/** @var string|NULL  interface name */
	private $implement;

	/** @var string|NULL  create | get */
	private $implementType;

	/** @var ContainerBuilder */
	private $containerBuilder;

	/**
	 * @return self
	 */
	public function setClass($class, array $args = [])
	{
		if ($this->containerBuilder) {
			$this->containerBuilder->invalidateClassList();
		}
		$this->class = $class ? ltrim($class, '\\') : NULL;
		if ($args) {
			$this->setFactory($class, $args);
		}
		return $this;
	}


	/**
	 * @return string|NULL
	 */
	public function getClass()
	{
		return $this->class;
	}


	/**
	 * @return self
	 */
	public function setFactory($factory, array $args = [])
	{
		if ($this->containerBuilder) {
			$this->containerBuilder->invalidateClassList();
		}
		$this->factory = $factory instanceof Statement ? $factory : new Statement($factory, $args);
		return $this;
	}


	/**
	 * @return Statement|NULL
	 */
	public function getFactory()
	{
		return $this->factory;
	}


	/**
	 * @return string|array|ServiceDefinition|NULL
	 */
	public function getEntity()
	{
		return $this->factory ? $this->factory->getEntity() : NULL;
	}


	/**
	 * @return self
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
	 * @return self
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
	public function getSetup()
	{
		return $this->setup;
	}


	/**
	 * @return self
	 */
	public function addSetup($entity, array $args = [])
	{
		$this->setup[] = $entity instanceof Statement ? $entity : new Statement($entity, $args);
		return $this;
	}


	/**
	 * @return self
	 */
	public function setParameters(array $params)
	{
		$this->parameters = $params;
		return $this;
	}


	/**
	 * @return array
	 */
	public function getParameters()
	{
		return $this->parameters;
	}


	/**
	 * @return self
	 */
	public function setTags(array $tags)
	{
		$this->tags = $tags;
		return $this;
	}


	/**
	 * @return array
	 */
	public function getTags()
	{
		return $this->tags;
	}


	/**
	 * @return self
	 */
	public function addTag($tag, $attr = TRUE)
	{
		$this->tags[$tag] = $attr;
		return $this;
	}


	/**
	 * @return mixed
	 */
	public function getTag($tag)
	{
		return isset($this->tags[$tag]) ? $this->tags[$tag] : NULL;
	}


	/**
	 * @param  bool
	 * @return self
	 */
	public function setAutowired($state = TRUE)
	{
		if ($this->containerBuilder) {
			$this->containerBuilder->invalidateClassList();
		}
		$this->autowired = (bool) $state;
		return $this;
	}


	/**
	 * @return bool
	 */
	public function isAutowired()
	{
		return $this->autowired;
	}


	/**
	 * @param  bool
	 * @return self
	 */
	public function setDynamic($state = TRUE)
	{
		$this->dynamic = (bool) $state;
		return $this;
	}


	/**
	 * @return bool
	 */
	public function isDynamic()
	{
		return $this->dynamic;
	}


	/**
	 * @param  string
	 * @return self
	 */
	public function setImplement($interface)
	{
		if ($this->containerBuilder) {
			$this->containerBuilder->invalidateClassList();
		}
		$this->implement = ltrim($interface, '\\');
		return $this;
	}


	/**
	 * @return string|NULL
	 */
	public function getImplement()
	{
		return $this->implement;
	}


	/**
	 * @param  string
	 * @return self
	 */
	public function setImplementType($type)
	{
		if (!in_array($type, ['get', 'create'], TRUE)) {
			throw new Nette\InvalidArgumentException('Argument must be get|create.');
		}
		$this->implementType = $type;
		return $this;
	}


	/**
	 * @return string|NULL
	 */
	public function getImplementType()
	{
		return $this->implementType;
	}


	/** @return self */
	public function setInject($state = TRUE)
	{
		//trigger_error(__METHOD__ . '() is deprecated.', E_USER_DEPRECATED);
		return $this->addTag(Extensions\InjectExtension::TAG_INJECT, $state);
	}


	/** @return bool|NULL */
	public function getInject()
	{
		//trigger_error(__METHOD__ . '() is deprecated.', E_USER_DEPRECATED);
		return $this->getTag(Extensions\InjectExtension::TAG_INJECT);
	}


	/**
	 * @param ContainerBuilder
	 * @internal
	 */
	public function setContainerBuilder(ContainerBuilder $containerBuilder)
	{
		$this->containerBuilder = $containerBuilder;
	}


	public function __clone()
	{
		$this->factory = unserialize(serialize($this->factory));
		$this->setup = unserialize(serialize($this->setup));
	}

}
