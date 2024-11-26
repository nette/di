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
 * @property-deprecated string|array|Definition|Reference|null $entity
 */
final class Statement implements Nette\Schema\DynamicParameter
{
	use Nette\SmartObject;

	public array $arguments;
	private string|array|Definition|Reference|null $entity;


	public function __construct(string|array|Definition|Reference|null $entity, array $arguments = [])
	{
		if (
			$entity !== null
			&& !is_string($entity) // Class, @service, not, tags, types, PHP literal, entity::member
			&& !$entity instanceof Definition
			&& !$entity instanceof Reference
			&& !(is_array($entity)
				&& array_keys($entity) === [0, 1]
				&& (is_string($entity[0])
					|| $entity[0] instanceof self
					|| $entity[0] instanceof Reference
					|| $entity[0] instanceof Definition)
			)) {
			throw new Nette\InvalidArgumentException('Argument is not valid Statement entity.');
		}

		// normalize Class::method to [Class, method]
		if (is_string($entity) && str_contains($entity, '::') && !str_contains($entity, '?')) {
			$entity = explode('::', $entity, 2);
		}

		if (is_string($entity) && str_starts_with($entity, '@')) { // normalize @service to Reference
			$entity = new Reference(substr($entity, 1));
		} elseif (is_array($entity) && is_string($entity[0]) && str_starts_with($entity[0], '@')) {
			$entity[0] = new Reference(substr($entity[0], 1));
		}

		$this->entity = $entity;
		$this->arguments = $arguments;
	}


	public function getEntity(): string|array|Definition|Reference|null
	{
		return $this->entity;
	}


    public function setEntityMember(string $value): void
    {
        if(!is_array($this->entity) || !isset($this->entity[1])){
            throw new Nette\InvalidStateException("Statement entity is not array, or entity member not exists.");

        }else{
            $this->entity[1] = $value;
        }
    }


    public function setEntityStatement(Statement $value): void
    {
        if(!is_array($this->entity) || !isset($this->entity[0])){
            throw new Nette\InvalidStateException("Statement entity is not array, or entity statement not exists.");

        }else{
            $this->entity[0] = $value;
        }
    }

}
