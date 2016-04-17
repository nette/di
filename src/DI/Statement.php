<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI;

use Nette;


/**
 * Assignment or calling statement.
 *
 * @property $entity
 */
class Statement
{
	use Nette\SmartObject;

	/** @var string|array|ServiceDefinition|NULL  class|method|$property */
	private $entity;

	/** @var array */
	public $arguments;


	/**
	 * @param  string|array|ServiceDefinition|NULL
	 */
	public function __construct($entity, array $arguments = [])
	{
		$this->setEntity($entity);
		$this->arguments = $arguments;
	}


	/**
	 * @param  string|array|ServiceDefinition|NULL
	 * @return self
	 */
	public function setEntity($entity)
	{
		if (!is_string($entity) && !(is_array($entity) && isset($entity[0], $entity[1]))
			&& !$entity instanceof ServiceDefinition && $entity !== NULL
		) {
			throw new Nette\InvalidArgumentException('Argument is not valid Statement entity.');
		}
		$this->entity = $entity;
		return $this;
	}


	public function getEntity()
	{
		return $this->entity;
	}

}
