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


	/** @deprecated Use setType() */
	public function setClass(?string $type)
	{
		$this->setType($type);
		if (func_num_args() > 1) {
			trigger_error(sprintf('Service %s: %s() second parameter $args is deprecated, use setFactory()', $this->getName(), __METHOD__), E_USER_DEPRECATED);
			if ($args = func_get_arg(1)) {
				$this->setFactory($type, $args);
			}
		}
		return $this;
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
		$this->factory = $factory instanceof Statement
			? $factory
			: new Statement($factory, $args);
		return $this;
	}


	public function getFactory(): Statement
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


	/** @deprecated */
	public function setParameters(array $params)
	{
		throw new Nette\DeprecatedException(sprintf('Service %s: %s() is deprecated.', $this->getName(), __METHOD__));
	}


	/** @deprecated */
	public function getParameters(): array
	{
		trigger_error(sprintf('Service %s: %s() is deprecated.', $this->getName(), __METHOD__), E_USER_DEPRECATED);
		return [];
	}


	/** @deprecated use $builder->addImportedDefinition(...) */
	public function setDynamic(): void
	{
		throw new Nette\DeprecatedException(sprintf('Service %s: %s() is deprecated, use $builder->addImportedDefinition(...)', $this->getName(), __METHOD__));
	}


	/** @deprecated use $builder->addFactoryDefinition(...) or addAccessorDefinition(...) */
	public function setImplement(): void
	{
		throw new Nette\DeprecatedException(sprintf('Service %s: %s() is deprecated, use $builder->addFactoryDefinition(...)', $this->getName(), __METHOD__));
	}


	/** @deprecated use addTag('nette.inject') */
	public function setInject(bool $state = true)
	{
		trigger_error(sprintf('Service %s: %s() is deprecated, use addTag(Nette\DI\Extensions\InjectExtension::TAG_INJECT)', $this->getName(), __METHOD__), E_USER_DEPRECATED);
		return $this->addTag(Nette\DI\Extensions\InjectExtension::TAG_INJECT, $state);
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
				throw new ServiceCreationException('Unknown service type, specify it or declare return type of factory.');
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
		$type = $this->getType();
		if (
			$type !== $entity
			&& !(is_array($entity) && $entity[0] instanceof Reference && $entity[0]->getValue() === Nette\DI\ContainerBuilder::THIS_CONTAINER)
			&& !(is_string($entity) && preg_match('#^[\w\\\\]+$#D', $entity) && is_subclass_of($entity, $type))
		) {
			$code .= (new Nette\PhpGenerator\Dumper)->format(
				"if (!\$service instanceof $type) {\n"
				. "\tthrow new Nette\\UnexpectedValueException(?);\n}\n",
				"Unable to create service '{$this->getName()}', value returned by factory is not $type type."
			);
		}

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
