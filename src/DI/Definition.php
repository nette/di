<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI;

use Nette;
use function is_array, is_string, sprintf;


/**
 * Abstract base for all service definition types used by ContainerBuilder.
 * @method string generateCode(Nette\DI\Compiler\PhpGenerator $generator)
 */
abstract class Definition
{
	private ?string $name = null;

	/** @var class-string|null */
	private ?string $type = null;

	/** @var array<string, mixed> */
	private array $tags = [];

	/** @var bool|class-string[] */
	private bool|array $autowired = true;

	/** @var ?(\Closure(Definition, string, mixed): void) */
	private ?\Closure $notifier = null;


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


	/**
	 * Returns the service name, which is assigned when the definition is registered in the builder.
	 * By default it throws for an unregistered definition (e.g. a factory result definition); pass
	 * throw: false to get null instead.
	 * @return ($throw is true ? string : ?string)
	 */
	final public function getName(bool $throw = true): ?string
	{
		return $this->name ?? ($throw
			? throw new Nette\InvalidStateException('The definition has no name yet; it is assigned when the definition is registered via add().')
			: null);
	}


	/** @param  class-string|null  $type */
	protected function setType(?string $type): static
	{
		$changed = $this->type !== $type;
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

		if ($changed) {
			$this->notify('type', $this->type);
		}

		return $this;
	}


	/** @return class-string|null */
	final public function getType(): ?string
	{
		return $this->type;
	}


	/** @param  array<string, mixed>  $tags */
	final public function setTags(array $tags): static
	{
		$this->tags = $tags;
		$this->notify('tags', $tags);
		return $this;
	}


	/** @return array<string, mixed> */
	final public function getTags(): array
	{
		return $this->tags;
	}


	final public function addTag(string $tag, mixed $attr = true): static
	{
		return $this->tag($tag, $attr);
	}


	final public function getTag(string $tag): mixed
	{
		return $this->tags[$tag] ?? null;
	}


	/**
	 * Adds a tag with an optional value.
	 */
	final public function tag(string $tag, mixed $value = true): static
	{
		$this->tags[$tag] = $value;
		$this->notify('tag', [$tag, $value]);
		return $this;
	}


	/**
	 * Removes a tag.
	 */
	final public function removeTag(string $tag): static
	{
		unset($this->tags[$tag]);
		$this->notify('removeTag', $tag);
		return $this;
	}


	/**
	 * Sets the autowiring mode. Pass false to disable, true to enable for all types, or one or more class names to restrict autowiring to specific types.
	 * @param  bool|class-string|class-string[]  $state
	 */
	final public function autowired(bool|string|array $state = true): static
	{
		$changed = $this->autowired !== $state;
		$this->autowired = is_string($state) || is_array($state)
			? (array) $state
			: $state;
		if ($changed) {
			$this->notify('autowired', $this->autowired);
		}

		return $this;
	}


	/**
	 * Sets the autowiring mode.
	 * @param  bool|class-string|class-string[]  $state
	 */
	final public function setAutowired(bool|string|array $state = true): static
	{
		return $this->autowired($state);
	}


	/** @return bool|class-string[] */
	final public function getAutowired(): bool|array
	{
		return $this->autowired;
	}


	public function exported(bool $state = true): static
	{
		return $this->tag('nette.exported', $state);
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


	abstract public function resolveType(Nette\DI\Compiler\Resolver $resolver): void;


	abstract public function complete(Nette\DI\Compiler\Resolver $resolver): void;


	//abstract public function generateCode(Nette\DI\Compiler\PhpGenerator $generator): string;


	/** @param (\Closure(Definition, string, mixed): void)|null $notifier */
	final public function setNotifier(?\Closure $notifier): void
	{
		$this->notifier = $notifier;
	}


	/**
	 * Reports a mutation to the notifier (set by ContainerBuilder): the resolution lock reacts to
	 * type/autowired changes, the journal records everything. A single hook, fanned out by the builder.
	 * @internal
	 */
	protected function notify(string $action, mixed $value = null): void
	{
		if ($this->notifier) {
			($this->notifier)($this, $action, $value);
		}
	}


	/********************* deprecated stuff from former ServiceDefinition ****************d*g**/


	/** @deprecated */
	public function generateMethod(Nette\PhpGenerator\Method $method, Nette\DI\Compiler\PhpGenerator $generator): void
	{
		$method->setBody($this->generateCode($generator));
	}


	/**
	 * @deprecated Use setType()
	 * @param class-string|null $type
	 * @return static
	 */
	public function setClass(?string $type)
	{
		return $this->setType($type);
	}


	/**
	 * @deprecated Use getType()
	 * @return class-string|null
	 */
	public function getClass(): ?string
	{
		return $this->getType();
	}


	/**
	 * @deprecated Use getAutowired()
	 * @return bool|class-string[]
	 */
	public function isAutowired()
	{
		return $this->autowired;
	}
}
