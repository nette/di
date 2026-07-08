<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Definitions;

use Nette;
use Nette\DI\Definition;
use Nette\DI\Expression;
use Nette\DI\Expressions\Instantiation;
use Nette\DI\Expressions\Reference;
use Nette\DI\ServiceCreationException;
use Nette\Utils\Strings;
use function count, is_string;


/**
 * Definition of standard service.
 *
 * @property ?string $class
 * @property Expression $factory
 * @property Statement[] $setup
 */
final class ServiceDefinition extends Definition
{
	use Nette\SmartObject;

	private ?bool $lazy = null;
	private Expression $creator;

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
	 * @param  string|array{string|Expression, string}|Definition|Expression  $factory
	 * @param  array<mixed>  $args
	 */
	public function setFactory(string|array|Definition|Expression $factory, array $args = []): static
	{
		return $this->setCreator($factory, $args);
	}


	/**
	 * Alias for getCreator()
	 */
	public function getFactory(): Expression
	{
		return $this->creator;
	}


	/**
	 * @param  string|array{string|Expression, string}|Definition|Expression  $creator
	 * @param  array<mixed>  $args
	 */
	public function setCreator(string|array|Definition|Expression $creator, array $args = []): static
	{
		$this->creator = $creator instanceof Expression && !$creator instanceof Reference
			? $creator
			: new Statement($creator, $args);
		$this->notify('creator', $this->creator);
		return $this;
	}


	public function getCreator(): Expression
	{
		return $this->creator;
	}


	/** @return string|array{string|Expression, string}|Definition|Reference|null */
	public function getEntity(): string|array|Definition|Reference|null
	{
		return $this->creator instanceof Statement
			? $this->creator->getEntity()
			: null;
	}


	/** @param  array<mixed>  $args */
	public function setArguments(array $args = []): static
	{
		if (!$this->creator instanceof Statement) {
			throw new Nette\InvalidStateException('Cannot pass arguments to this creator.');
		}

		$this->creator->arguments = $args;
		$this->notify('arguments', $args);
		return $this;
	}


	public function setArgument(int|string $key, mixed $value): static
	{
		if (!$this->creator instanceof Statement) {
			throw new Nette\InvalidStateException('Cannot pass arguments to this creator.');
		}

		$this->creator->arguments[$key] = $value;
		$this->notify('argument', [$key, $value]);
		return $this;
	}


	/**
	 * @param  Statement[]  $setup
	 */
	public function setSetup(array $setup): static
	{
		foreach ($setup as &$entity) {
			if (!$entity instanceof Statement) {
				throw new Nette\InvalidArgumentException('Argument must be Nette\DI\Definitions\Statement[].');
			}
			$entity = $this->prependSelf($entity);
		}

		$this->setup = $setup;
		$this->notify('setup', $setup);
		return $this;
	}


	/** @return Statement[] */
	public function getSetup(): array
	{
		return $this->setup;
	}


	/**
	 * @param  string|array{string|Reference|Statement, string}|Definition|Reference|Statement  $entity
	 * @param  array<mixed>  $args
	 */
	public function addSetup(string|array|Definition|Reference|Statement $entity, array $args = []): static
	{
		$entity = $entity instanceof Statement
			? $entity
			: new Statement($entity, $args);
		$this->setup[] = $step = $this->prependSelf($entity);
		$this->notify('setup', $step);
		return $this;
	}


	/**
	 * Adds a setup step (fluent DSL alias of addSetup()). The target is a method call on the
	 * service ('method'), a property assignment/append ('$prop' / '$prop[]'), a static call
	 * ([Class::class, 'method'] or 'Class::method'), or a statement expression carrying its own
	 * arguments (e.g. service('x')->method('m')). Arguments are passed as an array (use args()
	 * for the name: syntax), consistent with create()/call().
	 * @param  string|array{string, string}|Statement  $target
	 * @param  array<mixed>  $args
	 */
	public function setup(string|array|Statement $target, array $args = []): static
	{
		return $this->addSetup($target, $args);
	}


	/**
	 * Removes all setup steps.
	 */
	public function clearSetup(): static
	{
		$this->setup = [];
		$this->notify('clearSetup');
		return $this;
	}


	/**
	 * Enables or disables lazy instantiation.
	 */
	public function lazy(bool $state = true): static
	{
		$this->lazy = $state;
		$this->notify('lazy', $state);
		return $this;
	}


	/**
	 * Returns the lazy instantiation mode, or null when it has not been set.
	 */
	public function isLazy(): ?bool
	{
		return $this->lazy;
	}


	public function resolveType(Nette\DI\Compiler\Resolver $resolver): void
	{
		if ($this->creator instanceof Statement && !$this->creator->getEntity()) {
			$type = $this->getType();
			if (!$type) {
				throw new ServiceCreationException('Factory and type are missing in definition of service.');
			}

			$this->setCreator($type, $this->creator->arguments);

		} elseif (!$this->getType()) {
			$type = $this->creator->resolveType($resolver);
			if (!$type) {
				throw new ServiceCreationException('Unknown service type, specify it or declare return type of factory method.');
			}

			assert(class_exists($type) || interface_exists($type));
			$this->setType($type);
			$resolver->addDependency(new \ReflectionClass($type));
		}

		// auto-disable autowiring for aliases
		if ($this->getAutowired() === true && $this->getEntity() instanceof Reference) {
			$this->setAutowired(false);
		}
	}


	public function complete(Nette\DI\Compiler\Resolver $resolver): void
	{
		$entity = $this->getEntity();
		if (
			$entity instanceof Reference
			&& $this->creator instanceof Statement
			&& !$this->creator->arguments
			&& !$this->setup
		) {
			$ref = $entity->complete($resolver);
			$this->setCreator([new Reference(Nette\DI\ContainerBuilder::ThisContainer), 'getService'], [$ref->getValue()]);
		}

		$this->creator = $this->creator->complete($resolver);

		foreach ($this->setup as &$setup) {
			$setup = $setup->complete($resolver->withCurrentServiceAvailable());
		}
	}


	private function prependSelf(Statement $setup): Statement
	{
		return is_string($setup->getEntity()) && strpbrk($setup->getEntity(), ':@?\\') === false
			? new Statement([new Reference(Reference::Self), $setup->getEntity()], $setup->arguments)
			: $setup;
	}


	public function generateCode(Nette\DI\Compiler\PhpGenerator $generator): string
	{
		$lines = [];
		foreach ([$this->creator, ...$this->setup] as $stmt) {
			$lines[] = $stmt->generateCode($generator) . ";\n";
		}

		if ($this->canBeLazy() && !preg_grep('#func_get_arg|func_num_args#i', $lines)) { // latteFactory workaround
			assert($this->creator instanceof Instantiation); // canBeLazy() guarantees this
			$class = $this->creator->class;
			$lines[0] = (new \ReflectionClass($class))->hasMethod('__construct')
				? $generator->formatPhp("\$service->__construct(...?:);\n", [$this->creator->arguments])
				: '';
			return "return new ReflectionClass($class::class)->newLazyGhost(function (\$service) {\n"
				. Strings::indent(implode('', $lines))
				. '});';

		} elseif (count($lines) === 1) {
			return 'return ' . $lines[0];

		} else {
			return '$service = ' . implode('', $lines) . 'return $service;';
		}
	}


	private function canBeLazy(): bool
	{
		return $this->lazy
			&& $this->creator instanceof Instantiation // generateCode() runs on the completed creator
			&& ($this->creator->arguments || $this->setup)
			&& ($ancestor = ($tmp = class_parents($class = $this->creator->class)) ? array_pop($tmp) : $class)
			&& !(new \ReflectionClass($ancestor))->isInternal();
	}


	public function __clone()
	{
		parent::__clone();
		$this->creator = unserialize(serialize($this->creator));
		$this->setup = unserialize(serialize($this->setup));
	}
}


class_exists(Nette\DI\ServiceDefinition::class);
