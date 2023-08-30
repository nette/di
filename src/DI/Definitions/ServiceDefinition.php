<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Definitions;

use Nette;
use Nette\DI\ServiceCreationException;


/**
 * Definition of standard service.
 *
 * @property string|null $class
 * @property Statement $factory
 * @property Statement[] $setup
 */
final class ServiceDefinition extends Definition
{
	private Statement $creator;

	/** @var Statement[] */
	private array $setup = [];


	public function __construct()
	{
		$this->creator = new Statement(null);
	}


	public function setType(?string $type): static
	{
		return parent::setType($type);
	}


	/**
	 * Alias for setCreator()
	 */
	public function setFactory(string|array|Definition|Reference|Statement $factory, array $args = []): static
	{
		return $this->setCreator($factory, $args);
	}


	/**
	 * Alias for getCreator()
	 */
	public function getFactory(): Statement
	{
		return $this->getCreator();
	}


	public function setCreator(string|array|Definition|Reference|Statement $creator, array $args = []): static
	{
		$this->creator = $creator instanceof Statement
			? $creator
			: new Statement($creator, $args);
		return $this;
	}


	public function getCreator(): Statement
	{
		return $this->creator;
	}


	public function getEntity(): string|array|Definition|Reference|null
	{
		return $this->creator->getEntity();
	}


	public function setArguments(array $args = []): static
	{
		$this->creator->arguments = $args;
		return $this;
	}


	public function setArgument($key, $value): static
	{
		$this->creator->arguments[$key] = $value;
		return $this;
	}


	/**
	 * @param  Statement[]  $setup
	 */
	public function setSetup(array $setup): static
	{
		foreach ($setup as $v) {
			if (!$v instanceof Statement) {
				throw new Nette\InvalidArgumentException('Argument must be Nette\DI\Definitions\Statement[].');
			}
		}

		$this->setup = $setup;
		return $this;
	}


	/** @return Statement[] */
	public function getSetup(): array
	{
		return $this->setup;
	}


	public function addSetup(string|array|Definition|Reference|Statement $entity, array $args = []): static
	{
		$this->setup[] = $entity instanceof Statement
			? $entity
			: new Statement($entity, $args);
		return $this;
	}


	public function resolveType(Nette\DI\Resolver $resolver): void
	{
		if (!$this->getEntity()) {
			if (!$this->getType()) {
				throw new ServiceCreationException('Factory and type are missing in definition of service.');
			}

			$this->setCreator($this->getType(), $this->creator->arguments ?? []);

		} elseif (!$this->getType()) {
			$type = $resolver->resolveEntityType($this->creator);
			if (!$type) {
				throw new ServiceCreationException('Unknown service type, specify it or declare return type of factory method.');
			}

			$this->setType($type);
			$resolver->addDependency(new \ReflectionClass($type));
		}

		// auto-disable autowiring for aliases
		if ($this->getAutowired() === true && $this->getEntity() instanceof Reference) {
			$this->setAutowired(false);
		}
	}


	public function complete(Nette\DI\Resolver $resolver): void
	{
		$entity = $this->creator->getEntity();
		if ($entity instanceof Reference && !$this->creator->arguments && !$this->setup) {
			$ref = $resolver->normalizeReference($entity);
			$this->setCreator([new Reference(Nette\DI\ContainerBuilder::ThisContainer), 'getService'], [$ref->getValue()]);
		}

		$this->creator = $resolver->completeStatement($this->creator);

		foreach ($this->setup as &$setup) {
			if (
				is_string($setup->getEntity())
				&& strpbrk($setup->getEntity(), ':@?\\') === false
			) { // auto-prepend @self
				$setup = new Statement([new Reference(Reference::Self), $setup->getEntity()], $setup->arguments);
			}

			$setup = $resolver->completeStatement($setup, true);
		}
	}


	public function generateMethod(Nette\PhpGenerator\Method $method, Nette\DI\PhpGenerator $generator): void
	{
		$entity = $this->creator->getEntity();
		$code = $generator->formatStatement($this->creator) . ";\n";
		if (!$this->setup) {
			$method->setBody('return ' . $code);
			return;
		}

		$code = '$service = ' . $code;
		foreach ($this->setup as $setup) {
			$code .= $generator->formatStatement($setup) . ";\n";
		}

		$code .= 'return $service;';
		$method->setBody($code);
	}


	public function __clone()
	{
		parent::__clone();
		$this->creator = unserialize(serialize($this->creator));
		$this->setup = unserialize(serialize($this->setup));
	}
}


class_exists(Nette\DI\ServiceDefinition::class);
