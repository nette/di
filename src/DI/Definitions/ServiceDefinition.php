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
	/** @var Statement */
	private $factory;

	/** @var Statement[] */
	private $setup = [];


	public function __construct()
	{
		$this->factory = new Statement(null);
	}


	/** @return static */
	public function setType(?string $type)
	{
		return parent::setType($type);
	}


	/**
	 * @param  string|array|Definition|Reference|Statement  $factory
	 * @return static
	 */
	public function setFactory($factory, array $args = [])
	{
		return $this->setCreator($factory, $args);
	}


	public function getFactory(): Statement
	{
		return $this->getCreator();
	}


	/**
	 * @param  string|array|Definition|Reference|Statement  $factory
	 * @return static
	 */
	public function setCreator($factory, array $args = [])
	{
		$this->factory = $factory instanceof Statement
			? $factory
			: new Statement($factory, $args);
		return $this;
	}


	public function getCreator(): Statement
	{
		return $this->factory;
	}


	/** @return string|array|Definition|Reference|null */
	public function getEntity()
	{
		return $this->factory->getEntity();
	}


	/** @return static */
	public function setArguments(array $args = [])
	{
		$this->factory->arguments = $args;
		return $this;
	}


	/** @return static */
	public function setArgument($key, $value)
	{
		$this->factory->arguments[$key] = $value;
		return $this;
	}


	/**
	 * @param  Statement[]  $setup
	 * @return static
	 */
	public function setSetup(array $setup)
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


	/**
	 * @param  string|array|Definition|Reference|Statement  $entity
	 * @return static
	 */
	public function addSetup($entity, array $args = [])
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

			$this->setFactory($this->getType(), $this->factory->arguments ?? []);

		} elseif (!$this->getType()) {
			$type = $resolver->resolveEntityType($this->factory);
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
		$entity = $this->factory->getEntity();
		if ($entity instanceof Reference && !$this->factory->arguments && !$this->setup) {
			$ref = $resolver->normalizeReference($entity);
			$this->setFactory([new Reference(Nette\DI\ContainerBuilder::THIS_CONTAINER), 'getService'], [$ref->getValue()]);
		}

		$this->factory = $resolver->completeStatement($this->factory);

		foreach ($this->setup as &$setup) {
			if (
				is_string($setup->getEntity())
				&& strpbrk($setup->getEntity(), ':@?\\') === false
			) { // auto-prepend @self
				$setup = new Statement([new Reference(Reference::SELF), $setup->getEntity()], $setup->arguments);
			}

			$setup = $resolver->completeStatement($setup, true);
		}
	}


	public function generateMethod(Nette\PhpGenerator\Method $method, Nette\DI\PhpGenerator $generator): void
	{
		$entity = $this->factory->getEntity();
		$code = $generator->formatStatement($this->factory) . ";\n";
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
		$this->factory = unserialize(serialize($this->factory));
		$this->setup = unserialize(serialize($this->setup));
	}
}


class_exists(Nette\DI\ServiceDefinition::class);
