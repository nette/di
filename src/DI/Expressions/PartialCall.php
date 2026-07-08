<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Expressions;

use Nette\DI\Compiler\PhpGenerator;
use Nette\DI\Compiler\Resolver;
use Nette\DI\Expression;
use Nette\DI\ServiceCreationException;
use Nette\PhpGenerator as Php;
use function class_exists, is_string, method_exists, sprintf, str_starts_with;


/**
 * Partial function application, i.e. func(...), Class::method(...) or $object->method(...).
 */
final class PartialCall extends Expression
{
	public function __construct(
		public readonly Expression|string|null $target,
		public readonly string $name,
	) {
	}


	public function resolveType(Resolver $resolver): string
	{
		return \Closure::class;
	}


	public function complete(Resolver $resolver): self
	{
		if (is_string($this->target) && !Php\Helpers::isNamespaceIdentifier($this->target)) {
			throw new ServiceCreationException(sprintf("Expected a valid class name, '%s' given.", $this->target));

		} elseif ($this->target === null
			? !Php\Helpers::isNamespaceIdentifier($this->name)
			: !Php\Helpers::isIdentifier($this->name)
		) {
			throw new ServiceCreationException(sprintf(
				"Expected a valid %s name, '%s' given.",
				$this->target === null ? 'function' : 'method',
				$this->name,
			));
		}

		if (is_string($this->target)) {
			if (!class_exists($this->target)) {
				throw new ServiceCreationException(sprintf("Class '%s' not found.", $this->target));
			}

			// a missing method is tolerated because of magic __callStatic()
			if (method_exists($this->target, $this->name)
				&& !(new \ReflectionMethod($this->target, $this->name))->isPublic()
			) {
				throw new ServiceCreationException(sprintf('%s::%s() is not callable.', $this->target, $this->name));
			}
		}

		return $this->target instanceof Expression
			? new self($this->target->complete($resolver), $this->name)
			: $this;
	}


	public function generateCode(PhpGenerator $generator): string
	{
		if ($this->target instanceof Expression) {
			$inner = $this->target->generateCode($generator);
			if (str_starts_with($inner, 'new ')) {
				$inner = "($inner)";
			}

			return "$inner->$this->name(...)";
		}

		return $this->target === null
			? "$this->name(...)"
			: "$this->target::$this->name(...)";
	}


	public function transformValues(callable $cb): static
	{
		$name = $cb($this->name);
		return new self($cb($this->target), is_string($name) ? $name : $this->name);
	}
}
