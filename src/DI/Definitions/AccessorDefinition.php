<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Definitions;

use Nette;
use Nette\DI\Helpers;
use Nette\Utils\Type;
use function count, interface_exists, sprintf, str_starts_with, substr;


/**
 * Definition of an accessor service backed by a generated implementation of a user-defined interface with a single get() method.
 */
final class AccessorDefinition extends Definition
{
	private const MethodGet = 'get';

	private ?Reference $reference = null;


	public function setImplement(string $interface): static
	{
		if (!interface_exists($interface)) {
			throw new Nette\InvalidArgumentException(sprintf(
				"Service '%s': Interface '%s' not found.",
				$this->getName(),
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
				"Service '%s': Interface %s must have just one non-static method get().",
				$this->getName(),
				$interface,
			));
		} elseif ($method->getNumberOfParameters()) {
			throw new Nette\InvalidArgumentException(sprintf(
				"Service '%s': Method %s::get() must have no parameters.",
				$this->getName(),
				$interface,
			));
		}

		Helpers::ensureClassType(Type::fromReflection($method), "return type of $interface::get()");
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
			$type = $this->getType();
			if (!$type) {
				throw new Nette\DI\ServiceCreationException('Type is missing in definition of service.');
			}

			$method = new \ReflectionMethod($type, self::MethodGet);
			$name = Helpers::ensureClassType(Type::fromReflection($method), "return type of $type::" . self::MethodGet . '()');
			$this->setReference($name);
		}

		assert($this->reference !== null); // setReference() above or pre-existing
		$this->reference = $resolver->normalizeReference($this->reference);
	}


	public function generateCode(Nette\DI\PhpGenerator $generator): string
	{
		$type = $this->getType();
		assert($type !== null);

		$class = (new Nette\PhpGenerator\ClassType)
			->addImplement($type);

		$class->addMethod('__construct')
			->addPromotedParameter('container')
				->setPrivate()
				->setType($generator->getClassName());

		$rm = new \ReflectionMethod($type, self::MethodGet);

		assert($this->reference !== null);
		$class->addMethod(self::MethodGet)
			->setBody('return $this->container->getService(?);', [$this->reference->getValue()])
			->setReturnType((string) Type::fromReflection($rm));

		return 'return new class ($this) ' . $class . ';';
	}
}
