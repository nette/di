<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Definitions;

use Nette;
use Nette\Utils\Reflection;


/**
 * Multi accessor/factory definition.
 */
final class LocatorDefinition extends Definition
{
	private const METHOD_GET = 'get';
	private const METHOD_CREATE = 'create';

	/** @var Reference[] */
	private $references = [];

	/** @var string|null */
	private $tagged;

	/** @var bool */
	private $methodName;


	/**
	 * @return static
	 */
	public function setImplement(string $type)
	{
		if (!interface_exists($type)) {
			throw new Nette\InvalidArgumentException("Service '{$this->getName()}': Interface '$type' not found.");
		}
		$rc = new \ReflectionClass($type);
		$method = $rc->getMethods()[0] ?? null;
		if (!$method || $method->isStatic() || !in_array($method->getName(), [self::METHOD_GET, self::METHOD_CREATE], true) || count($rc->getMethods()) > 1) {
			throw new Nette\InvalidArgumentException("Service '{$this->getName()}': Interface $type must have just one non-static method create() or get().");
		} elseif ($method->getNumberOfParameters() !== 1) {
			throw new Nette\InvalidArgumentException("Service '{$this->getName()}': Method $type::{$method->getName()}() must have one parameter.");
		}
		$this->methodName = $method->getName();
		return parent::setType($type);
	}


	public function getImplement(): ?string
	{
		return $this->getType();
	}


	/**
	 * @return static
	 */
	public function setReferences(array $references)
	{
		$this->references = [];
		foreach ($references as $name => $ref) {
			$this->references[$name] = substr($ref, 0, 1) === '@'
				? new Reference(substr($ref, 1))
				: Reference::fromType($ref);
		}
		return $this;
	}


	/**
	 * @return Reference[]
	 */
	public function getReferences(): array
	{
		return $this->references;
	}


	/**
	 * @return static
	 */
	public function setTagged(?string $tagged)
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
					trigger_error("Service '{$this->getName()}': duplicated tag '$this->tagged' with value '$tag'.", E_USER_NOTICE);
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
		$rm = new \ReflectionMethod($this->getType(), $this->methodName);
		$nullable = $rm->getReturnType()->allowsNull();

		$class = (new Nette\PhpGenerator\ClassType)
			->addImplement($this->getType());

		$class->addProperty('container')
			->setVisibility('private');

		$class->addProperty('mapping', array_map(function ($item) { return $item->getValue(); }, $this->references))
			->setVisibility('private');

		$class->addMethod('__construct')
			->addBody('$this->container = $container;')
			->addParameter('container')
			->setTypeHint($generator->getClassName());

		$body = 'if (!isset($this->mapping[$name])) {
	' . ($nullable ? 'return null;' : 'throw new Nette\DI\MissingServiceException("Service \'$name\' is not defined.");') . '
}
return $this->container->' . $this->methodName . 'Service($this->mapping[$name]);';

		$class->addMethod($this->methodName)
			->setReturnType(Reflection::getReturnType($rm))
			->setReturnNullable($nullable)
			->setBody($body)
			->addParameter('name');

		$method->setBody('return new class ($this) ' . $class . ';');
	}
}
