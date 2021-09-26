<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Definitions;

use Nette;
use Nette\DI\ServiceCreationException;
use Nette\Utils\Type;


/**
 * Accessor definition.
 */
final class AccessorDefinition extends Definition
{
	private const METHOD_GET = 'get';

	/** @var Reference|null */
	private $reference;


	/** @return static */
	public function setImplement(string $interface)
	{
		if (!interface_exists($interface)) {
			throw new Nette\InvalidArgumentException(sprintf(
				"Service '%s': Interface '%s' not found.",
				$this->getName(),
				$interface
			));
		}
		$rc = new \ReflectionClass($interface);

		$method = $rc->getMethods()[0] ?? null;
		if (
			!$method
			|| $method->isStatic()
			|| $method->getName() !== self::METHOD_GET
			|| count($rc->getMethods()) > 1
		) {
			throw new Nette\InvalidArgumentException(sprintf(
				"Service '%s': Interface %s must have just one non-static method get().",
				$this->getName(),
				$interface
			));
		} elseif ($method->getNumberOfParameters()) {
			throw new Nette\InvalidArgumentException(sprintf(
				"Service '%s': Method %s::get() must have no parameters.",
				$this->getName(),
				$interface
			));
		}
		return parent::setType($interface);
	}


	public function getImplement(): ?string
	{
		return $this->getType();
	}


	/**
	 * @param  string|Reference  $reference
	 * @return static
	 */
	public function setReference($reference)
	{
		if ($reference instanceof Reference) {
			$this->reference = $reference;
		} else {
			$this->reference = substr($reference, 0, 1) === '@'
				? new Reference(substr($reference, 1))
				: Reference::fromType($reference);
		}
		return $this;
	}


	public function getReference(): ?Reference
	{
		return $this->reference;
	}


	public function resolveType(Nette\DI\Resolver $resolver): void
	{
	}


	public function complete(Nette\DI\Resolver $resolver): void
	{
		if (!$this->reference) {
			$interface = $this->getType();
			$method = new \ReflectionMethod($interface, self::METHOD_GET);
			$returnType = Nette\DI\Helpers::getReturnType($method);

			if (!$returnType) {
				throw new ServiceCreationException(sprintf('Method %s::get() has no return type or annotation @return.', $interface));
			} elseif (!class_exists($returnType) && !interface_exists($returnType)) {
				throw new ServiceCreationException(sprintf(
					"Class '%s' not found.\nCheck the return type or annotation @return of the %s::get() method.",
					$returnType,
					$interface
				));
			}
			$this->setReference($returnType);
		}

		$this->reference = $resolver->normalizeReference($this->reference);
	}


	public function generateMethod(Nette\PhpGenerator\Method $method, Nette\DI\PhpGenerator $generator): void
	{
		$class = (new Nette\PhpGenerator\ClassType)
			->addImplement($this->getType());

		$class->addProperty('container')
			->setPrivate();

		$class->addMethod('__construct')
			->addBody('$this->container = $container;')
			->addParameter('container')
			->setType($generator->getClassName());

		$rm = new \ReflectionMethod($this->getType(), self::METHOD_GET);

		$class->addMethod(self::METHOD_GET)
			->setBody('return $this->container->getService(?);', [$this->reference->getValue()])
			->setReturnType((string) Type::fromReflection($rm));

		$method->setBody('return new class ($this) ' . $class . ';');
	}
}
