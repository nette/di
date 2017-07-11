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
 * @property string|array|ServiceDefinition|null $entity
 */
class Statement
{
	use Nette\SmartObject;

	/** @var array */
	public $arguments;

	/** @var string|array|ServiceDefinition|null  class|method|$property */
	private $entity;


	/**
	 * @param  string|array|ServiceDefinition|null
	 */
	public function __construct($entity, array $arguments = [])
	{
		if (!is_string($entity) && !(is_array($entity) && isset($entity[0], $entity[1]))
			&& !$entity instanceof ServiceDefinition && $entity !== null
		) {
			throw new Nette\InvalidArgumentException('Argument is not valid Statement entity.');
		}
		$this->entity = $entity;
		$this->arguments = $arguments;
	}


	/** @deprecated */
	public function setEntity($entity)
	{
		trigger_error(__METHOD__ . ' is deprecated, change Statement object itself.', E_USER_DEPRECATED);
		$this->__construct($entity, $this->arguments);
		return $this;
	}


	public function getEntity()
	{
		return $this->entity;
	}
}
