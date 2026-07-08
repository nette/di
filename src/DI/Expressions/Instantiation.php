<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Expressions;

use Nette\DI\Compiler\PhpGenerator;
use Nette\DI\Compiler\Resolver;
use Nette\DI\Definitions\Statement;
use Nette\DI\ServiceCreationException;
use function class_exists, interface_exists, is_string, sprintf;


/**
 * Class instantiation, i.e. new Class(...arguments) with constructor autowiring.
 */
final class Instantiation extends Statement
{
	public function __construct(
		public readonly string $class,
		/** @var array<mixed> */
		public array $arguments = [],
	) {
	}


	public function resolveType(Resolver $resolver): string
	{
		// validated at resolve-time, before the class's factory method exists
		if (!class_exists($this->class)) {
			throw new ServiceCreationException(sprintf(
				interface_exists($this->class)
					? "Interface %s can not be used as 'create' or 'factory', did you mean 'implement'?"
					: "Class '%s' not found.",
				$this->class,
			));
		}

		return $this->class;
	}


	public function complete(Resolver $resolver): self
	{
		if (!class_exists($this->class)) {
			throw new ServiceCreationException(sprintf("Class '%s' not found.", $this->class));
		} elseif ((new \ReflectionClass($this->class))->isAbstract()) {
			throw new ServiceCreationException(sprintf('Class %s is abstract.', $this->class));
		} elseif (
			($constructor = (new \ReflectionClass($this->class))->getConstructor()) !== null
			&& !$constructor->isPublic()
		) {
			throw new ServiceCreationException(sprintf('Class %s has %s constructor.', $this->class, $constructor->isProtected() ? 'protected' : 'private'));
		}

		$arguments = $this->arguments;
		if ($constructor) {
			$arguments = $resolver->autowireArguments($constructor, $arguments, $resolver);
			$resolver->addDependency($constructor);
		} elseif ($this->arguments) {
			throw new ServiceCreationException(sprintf(
				'Unable to pass arguments, class %s has no constructor.',
				$this->class,
			));
		}

		$arguments = $resolver->resolveArguments($arguments, $this->class . '::__construct()');
		return new self($this->class, $arguments);
	}


	public function generateCode(PhpGenerator $generator): string
	{
		return $this->arguments
			? $generator->formatPhp("new $this->class(...?:)", [$this->arguments])
			: $generator->formatPhp("new $this->class", []);
	}


	public function transformValues(callable $cb): static
	{
		$class = $cb($this->class);
		return new self(is_string($class) ? $class : $this->class, $cb($this->arguments));
	}


	public function getEntity(): string
	{
		return $this->class;
	}
}
