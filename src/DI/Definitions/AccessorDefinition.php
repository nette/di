<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Definitions;

use Nette;
use Nette\DI\Helpers;
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
				"[%s]\nInterface '%s' not found.",
				$this->getDescriptor(),
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
				"[%s]\nInterface %s must have just one non-static method get().",
				$this->getDescriptor(),
				$interface
			));
		} elseif ($method->getNumberOfParameters()) {
			throw new Nette\InvalidArgumentException(sprintf(
				"[%s]\nMethod %s::get() must have no parameters.",
				$this->getDescriptor(),
				$interface
			));
		}
		try {
			Helpers::ensureClassType(Type::fromReflection($method), "return type of $interface::get()", $this->getDescriptor());
		} catch (Nette\DI\ServiceCreationException $e) {
			trigger_error($e->getMessage(), E_USER_DEPRECATED);
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
			$type = Type::fromReflection($method) ?? Helpers::getReturnTypeAnnotation($method);
			$this->setReference(Helpers::ensureClassType($type, "return type of $interface::get()"));
		}

		$this->reference = $resolver->normalizeReference($this->reference);
	}


	public function generateMethod(Nette\PhpGenerator\Method $method, Nette\DI\PhpGenerator $generator): void
	{
		$class = (new Nette\PhpGenerator\ClassType)
			->addImplement($this->getType());

		$containerType = $generator->getClassName();
		$container = $class->addProperty('container')
			->setPrivate();
		if (PHP_VERSION_ID >= 74000) {
			$container->setType($containerType);
		}

		$class->addMethod('__construct')
			->addBody('$this->container = $container;')
			->addParameter('container')
			->setType($containerType);

		$rm = new \ReflectionMethod($this->getType(), self::METHOD_GET);

		$class->addMethod(self::METHOD_GET)
			->setBody('return $this->container->getService(?);', [$this->reference->getValue()])
			->setReturnType((string) Type::fromReflection($rm));

		$method->setBody('return new class ($this) ' . $class . ';');
	}
}
