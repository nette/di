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
use function count, interface_exists, sprintf, str_starts_with, substr;


/**
 * Accessor definition.
 */
final class AccessorDefinition extends Definition
{
	private const MethodGet = 'get';

	private ?Reference $reference = null;


	public function setImplement(string $interface): static
	{
		if (!interface_exists($interface)) {
			throw new Nette\InvalidArgumentException(sprintf(
				"[%s]\nInterface '%s' not found.",
				$this->getDescriptor(),
				$interface,
			));
		}

		$rc = new \ReflectionClass($interface);

		$method = $rc->getMethods()[0] ?? null;
		if (
			!$method
			|| $method->isStatic()
			|| $method->getName() !== self::MethodGet
			|| count($rc->getMethods()) > 1
		) {
			throw new Nette\InvalidArgumentException(sprintf(
				"[%s]\nInterface %s must have just one non-static method get().",
				$this->getDescriptor(),
				$interface,
			));
		} elseif ($method->getNumberOfParameters()) {
			throw new Nette\InvalidArgumentException(sprintf(
				"[%s]\nMethod %s::get() must have no parameters.",
				$this->getDescriptor(),
				$interface,
			));
		}

		Helpers::ensureClassType(Type::fromReflection($method), "return type of $interface::get()", $this->getDescriptor());
		return parent::setType($interface);
	}


	public function getImplement(): ?string
	{
		return $this->getType();
	}


	public function setReference(string|Reference $reference): static
	{
		if ($reference instanceof Reference) {
			$this->reference = $reference;
		} else {
			$this->reference = str_starts_with($reference, '@')
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
			if (!$this->getType()) {
				throw new Nette\DI\ServiceCreationException('Type is missing in definition of service.');
			}

			$method = new \ReflectionMethod($this->getType(), self::MethodGet);
			$this->setReference(Type::fromReflection($method)->getSingleName());
		}

		$this->reference->complete($resolver);
	}


	public function generateCode(Nette\DI\PhpGenerator $generator): string
	{
		$class = (new Nette\PhpGenerator\ClassType)
			->addImplement($this->getType());

		$class->addMethod('__construct')
			->addPromotedParameter('container')
				->setPrivate()
				->setType($generator->getClassName());

		$rm = new \ReflectionMethod($this->getType(), self::MethodGet);

		$class->addMethod(self::MethodGet)
			->setBody('return $this->container->getService(?);', [$this->reference->getValue()])
			->setReturnType((string) Type::fromReflection($rm));

		return 'return new class ($this) ' . $class . ';';
	}
}
