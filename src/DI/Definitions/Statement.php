<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Definitions;

use Nette;


/**
 * Assignment or calling statement.
 *
 * @property string|array|ServiceDefinition|Reference|null $entity
 */
final class Statement
{
	use Nette\SmartObject;

	/** @var array */
	public $arguments;

	/** @var string|array|ServiceDefinition|null */
	private $entity;


	/**
	 * @param  string|array|ServiceDefinition|Reference|null  $entity
	 */
	public function __construct($entity, array $arguments = [])
	{
		if (
			!is_string($entity) // Class, @service, not, PHP literal, entity::member
			&& !(is_array($entity) && isset($entity[0], $entity[1])) // [Class | @service | '' | Statement | ServiceDefinition | Reference, method | $property | $appender]
			&& !$entity instanceof ServiceDefinition
			&& !$entity instanceof Reference
			&& $entity !== null
		) {
			throw new Nette\InvalidArgumentException('Argument is not valid Statement entity.');
		}
		$this->entity = $entity;
		$this->arguments = $arguments;
	}


	/** @return string|array|ServiceDefinition|null */
	public function getEntity()
	{
		return $this->entity;
	}
}
