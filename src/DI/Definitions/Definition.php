<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Definitions;

use Nette;


/**
 * Definition used by ContainerBuilder.
 */
abstract class Definition
{
	private ?string $name = null;
	private ?string $type = null;
	private array $tags = [];

	/** @var bool|string[] */
	private bool|array $autowired = true;

	/** @var ?callable */
	private $notifier;


	/**
	 * @internal  This is managed by ContainerBuilder and should not be called by user
	 */
	final public function setName(string $name): static
	{
		if ($this->name) {
			throw new Nette\InvalidStateException('Name already has been set.');
		}

		$this->name = $name;
		return $this;
	}


	final public function getName(): ?string
	{
		return $this->name;
	}


	protected function setType(?string $type): static
	{
		if ($this->autowired && $this->notifier && $this->type !== $type) {
			($this->notifier)();
		}

		if ($type === null) {
			$this->type = null;
		} elseif (!class_exists($type) && !interface_exists($type)) {
			throw new Nette\InvalidArgumentException(sprintf(
				"Service '%s': Class or interface '%s' not found.",
				$this->name,
				$type,
			));
		} else {
			$this->type = Nette\DI\Helpers::normalizeClass($type);
		}

		return $this;
	}


	final public function getType(): ?string
	{
		return $this->type;
	}


	final public function setTags(array $tags): static
	{
		$this->tags = $tags;
		return $this;
	}


	final public function getTags(): array
	{
		return $this->tags;
	}


	final public function addTag(string $tag, mixed $attr = true): static
	{
		$this->tags[$tag] = $attr;
		return $this;
	}


	final public function getTag(string $tag): mixed
	{
		return $this->tags[$tag] ?? null;
	}


	final public function setAutowired(bool|string|array $state = true): static
	{
		if ($this->notifier && $this->autowired !== $state) {
			($this->notifier)();
		}

		$this->autowired = is_string($state) || is_array($state)
			? (array) $state
			: $state;
		return $this;
	}


	/** @return bool|string[] */
	final public function getAutowired(): bool|array
	{
		return $this->autowired;
	}


	public function setExported(bool $state = true): static
	{
		return $this->addTag('nette.exported', $state);
	}


	public function isExported(): bool
	{
		return (bool) $this->getTag('nette.exported');
	}


	public function __clone()
	{
		$this->notifier = $this->name = null;
	}


	/********************* life cycle ****************d*g**/


	abstract public function resolveType(Nette\DI\Resolver $resolver): void;


	abstract public function complete(Nette\DI\Resolver $resolver): void;


	abstract public function generateMethod(Nette\PhpGenerator\Method $method, Nette\DI\PhpGenerator $generator): void;


	final public function setNotifier(?callable $notifier): void
	{
		$this->notifier = $notifier;
	}


	/********************* deprecated stuff from former ServiceDefinition ****************d*g**/


	/** @deprecated Use setType() */
	public function setClass(?string $type)
	{
		return $this->setType($type);
	}


	/** @deprecated Use getType() */
	public function getClass(): ?string
	{
		return $this->getType();
	}


	/** @deprecated Use '$def instanceof Nette\DI\Definitions\ImportedDefinition' */
	public function isDynamic(): bool
	{
		trigger_error(sprintf('Service %s: %s() is deprecated, use "instanceof ImportedDefinition".', $this->getName(), __METHOD__), E_USER_DEPRECATED);
		return false;
	}


	/** @deprecated Use Nette\DI\Definitions\FactoryDefinition or AccessorDefinition */
	public function getImplement(): ?string
	{
		trigger_error(sprintf('Service %s: %s() is deprecated.', $this->getName(), __METHOD__), E_USER_DEPRECATED);
		return null;
	}


	/** @deprecated Use getAutowired() */
	public function isAutowired()
	{
		return $this->autowired;
	}
}
