<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Definitions;

use Nette;
use Nette\DI\ServiceCreationException;
use Nette\Utils\Reflection;


/**
 * Accessor definition.
 */
final class AccessorDefinition extends Definition
{
	private const METHOD_GET = 'get';

	/** @var Reference|null */
	private $reference;


	/**
	 * @return static
	 */
	public function setImplement(?string $type)
	{
		if ($type !== null && !interface_exists($type)) {
			throw new Nette\InvalidArgumentException("Service '{$this->getName()}': Interface '$type' not found.");
		}
		$rc = new \ReflectionClass($type);
		$method = $rc->hasMethod(self::METHOD_GET) ? $rc->getMethod(self::METHOD_GET) : null;
		if (count($rc->getMethods()) !== 1 || !$method || $method->isStatic()) {
			throw new Nette\InvalidArgumentException("Service '{$this->getName()}': Interface $type must have just one non-static method get().");
		} elseif ($method->getParameters()) {
			throw new ServiceCreationException("Service '{$this->getName()}': Method $type::get() must have no arguments.");
		}
		return parent::setType($type);
	}


	public function getImplement(): ?string
	{
		return $this->getType();
	}


	/**
	 * @return static
	 */
	public function setReference(string $reference)
	{
		$this->reference = substr($reference, 0, 1) === '@'
			? new Reference(substr($reference, 1))
			: Reference::fromType($reference);
		return $this;
	}


	public function getReference(): ?Reference
	{
		return $this->reference;
	}


	public function resolveType(Nette\DI\Resolver $resolver): void
	{
		if ($this->reference) {
			return;
		}

		$interface = $this->getType();
		$method = new \ReflectionMethod($interface, self::METHOD_GET);
		$returnType = Nette\DI\Helpers::getReturnType($method);

		if (!$returnType) {
			throw new ServiceCreationException("Method $interface::get() has not return type hint or annotation @return.");
		} elseif (!class_exists($returnType)) {
			throw new ServiceCreationException("Check a type hint or annotation @return of the $interface::get() method, class '$returnType' cannot be found.");
		}
		$this->setReference($returnType);
	}


	public function complete(Nette\DI\Resolver $resolver): void
	{
		$this->reference = $resolver->normalizeReference($this->reference);
	}


	public function generateMethod(Nette\PhpGenerator\Method $method, Nette\DI\PhpGenerator $generator): void
	{
		$class = (new Nette\PhpGenerator\ClassType)
			->addImplement($this->getType());

		$class->addProperty('container')
			->setVisibility('private');

		$class->addMethod('__construct')
			->addBody('$this->container = $container;')
			->addParameter('container')
			->setTypeHint($generator->getClassName());

		$rm = new \ReflectionMethod($this->getType(), self::METHOD_GET);

		$class->addMethod(self::METHOD_GET)
			->setBody('return $this->container->getService(?);', [$this->reference->getValue()])
			->setReturnType(Reflection::getReturnType($rm));

		$method->setBody('return new class ($this) ' . $class . ';');
	}
}
