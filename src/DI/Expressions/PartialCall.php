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
use function array_keys, array_map, class_exists, implode, is_string, method_exists, sprintf, str_starts_with;


/**
 * Partial function application, i.e. func(...), Class::method(?, $bound) or $object->method(...).
 */
final class PartialCall extends Expression
{
	public function __construct(
		public readonly Expression|string|null $target,
		public readonly string $name,
		/** @var array<mixed>  bound values (may be @references / expressions) interleaved with ArgumentPlaceholder markers */
		public readonly array $arguments = [ArgumentPlaceholder::Variadic],
	) {
	}


	public function resolveType(Resolver $resolver): string
	{
		return \Closure::class;
	}


	public function complete(Resolver $resolver): self
	{
		if (!array_filter($this->arguments, fn($arg): bool => $arg instanceof ArgumentPlaceholder)) {
			throw new ServiceCreationException(sprintf('First-class callable %s must contain at least one placeholder (? or ...).', $this->usedIn($this->target)));

		} elseif (is_string($this->target) && !Php\Helpers::isNamespaceIdentifier($this->target)) {
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

		$target = $this->target instanceof Expression
			? $this->target->complete($resolver)
			: $this->target;

		$arguments = $resolver->resolveArguments($this->arguments, $this->usedIn($target));
		return new self($target, $this->name, $arguments);
	}


	public function generateCode(PhpGenerator $generator): string
	{
		$args = implode(', ', array_map(
			fn($key, $arg): string => (is_string($key) ? "$key: " : '') . match ($arg) {
				ArgumentPlaceholder::Single => '?',
				ArgumentPlaceholder::Variadic => '...',
				default => $generator->formatPhp('?', [$arg]),
			},
			array_keys($this->arguments),
			$this->arguments,
		));

		if ($this->target instanceof Expression) {
			$inner = $this->target->generateCode($generator);
			if (str_starts_with($inner, 'new ')) {
				$inner = "($inner)";
			}

			return "$inner->$this->name($args)";
		}

		return $this->target === null
			? "$this->name($args)"
			: "$this->target::$this->name($args)";
	}


	public function transformValues(callable $cb): static
	{
		$name = $cb($this->name);
		return new self($cb($this->target), is_string($name) ? $name : $this->name, $cb($this->arguments));
	}


	/** Human-readable callee for error messages, e.g. Foo::bar(), @svc::bar() or bar(). */
	private function usedIn(Expression|string|null $target): string
	{
		return match (true) {
			$target instanceof Reference => '@' . $target->getValue() . '::',
			is_string($target) => $target,
			default => '',
		} . $this->name . '()';
	}
}
