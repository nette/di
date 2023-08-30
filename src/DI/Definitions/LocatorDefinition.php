<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Definitions;

use Nette;


/**
 * Multi accessor/factory definition.
 */
final class LocatorDefinition extends Definition
{
	/** @var Reference[] */
	private array $references = [];
	private ?string $tagged = null;


	public function setImplement(string $interface): static
	{
		if (!interface_exists($interface)) {
			throw new Nette\InvalidArgumentException(sprintf("[%s]\nInterface '%s' not found.", $this->getDescriptor(), $interface));
		}

		$methods = (new \ReflectionClass($interface))->getMethods();
		if (!$methods) {
			throw new Nette\InvalidArgumentException(sprintf("[%s]\nInterface %s must have at least one method.", $this->getDescriptor(), $interface));
		}

		foreach ($methods as $method) {
			if ($method->isStatic() || !(
				(preg_match('#^(get|create)$#', $method->name) && $method->getNumberOfParameters() === 1)
				|| (preg_match('#^(get|create)[A-Z]#', $method->name) && $method->getNumberOfParameters() === 0)
			)) {
				throw new Nette\InvalidArgumentException(sprintf(
					"[%s]\nMethod %s::%s() does not meet the requirements: is create(\$name), get(\$name), create*() or get*() and is non-static.",
					$this->getDescriptor(),
					$interface,
					$method->name,
				));
			}

			if ($method->getNumberOfParameters() === 0) {
				Nette\DI\Helpers::ensureClassType(
					Nette\Utils\Type::fromReflection($method),
					"return type of $interface::$method->name()",
					$this->getDescriptor(),
					allowNullable: true,
				);
			}
		}

		return parent::setType($interface);
	}


	public function getImplement(): ?string
	{
		return $this->getType();
	}


	public function setReferences(array $references): static
	{
		$this->references = [];
		foreach ($references as $name => $ref) {
			$this->references[$name] = str_starts_with($ref, '@')
				? new Reference(substr($ref, 1))
				: Reference::fromType($ref);
		}

		return $this;
	}


	/** @return Reference[] */
	public function getReferences(): array
	{
		return $this->references;
	}


	public function setTagged(?string $tagged): static
	{
		$this->tagged = $tagged;
		return $this;
	}


	public function getTagged(): ?string
	{
		return $this->tagged;
	}


	public function resolveType(Nette\DI\Resolver $resolver): void
	{
	}


	public function complete(Nette\DI\Resolver $resolver): void
	{
		if ($this->tagged !== null) {
			$this->references = [];
			foreach ($resolver->getContainerBuilder()->findByTag($this->tagged) as $name => $tag) {
				if (isset($this->references[$tag])) {
					trigger_error(sprintf(
						"[%s]\nDuplicated tag '%s' with value '%s'.",
						$this->getDescriptor(),
						$this->tagged,
						$tag,
					), E_USER_NOTICE);
				}

				$this->references[$tag] = new Reference($name);
			}
		}

		foreach ($this->references as $name => $ref) {
			$this->references[$name] = $resolver->normalizeReference($ref);
		}
	}


	public function generateMethod(Nette\PhpGenerator\Method $method, Nette\DI\PhpGenerator $generator): void
	{
		$class = (new Nette\PhpGenerator\ClassType)
			->addImplement($this->getType());

		$class->addMethod('__construct')
			->addPromotedParameter('container')
				->setPrivate()
				->setType($generator->getClassName());

		foreach ((new \ReflectionClass($this->getType()))->getMethods() as $rm) {
			preg_match('#^(get|create)(.*)#', $rm->name, $m);
			$name = lcfirst($m[2]);
			$nullable = $rm->getReturnType()->allowsNull();

			$methodInner = $class->addMethod($rm->name)
				->setReturnType((string) Nette\Utils\Type::fromReflection($rm));

			if (!$name) {
				$class->addProperty('mapping', array_map(fn($item) => $item->getValue(), $this->references))
					->setPrivate();

				$methodInner->setBody('if (!isset($this->mapping[$name])) {
	' . ($nullable ? 'return null;' : 'throw new Nette\DI\MissingServiceException("Service \'$name\' is not defined.");') . '
}
return $this->container->' . $m[1] . 'Service($this->mapping[$name]);')
					->addParameter('name');

			} elseif (isset($this->references[$name])) {
				$ref = $this->references[$name]->getValue();
				if ($m[1] === 'get') {
					$methodInner->setBody('return $this->container->getService(?);', [$ref]);
				} else {
					$methodInner->setBody('return $this->container->?();', [Nette\DI\Container::getMethodName($ref)]);
				}
			} else {
				$methodInner->setBody($nullable ? 'return null;' : 'throw new Nette\DI\MissingServiceException("Service is not defined.");');
			}
		}

		$method->setBody('return new class ($this) ' . $class . ';');
	}
}
