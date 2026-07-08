<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Expressions;

use Nette;
use Nette\DI\Compiler\PhpGenerator;
use Nette\DI\Compiler\Resolver;
use Nette\DI\Definitions\Statement;
use Nette\DI\Expression;
use Nette\DI\ServiceCreationException;
use Nette\PhpGenerator as Php;
use Nette\Utils\Callback;
use function class_exists, function_exists, in_array, interface_exists, is_string, preg_match, sprintf, str_starts_with;


/**
 * Call of a global function, a static method or a method on the result of another expression.
 */
final class Call extends Statement
{
	use Chaining;

	public function __construct(
		/** null for a global function, class name for a static call, expression for a method call */
		public readonly Expression|string|null $target,
		public readonly string $name,
		/** @var array<mixed> */
		public array $arguments = [],
	) {
	}


	public function resolveType(Resolver $resolver): ?string
	{
		if ($this->target instanceof Expression) {
			$targetType = $this->target->resolveType($resolver);
			if (!$targetType) {
				return null;
			}
		} else {
			$targetType = $this->target;
		}

		try {
			$reflection = Callback::toReflection($targetType === null ? $this->name : [$targetType, $this->name]);
		} catch (\ReflectionException $e) {
			throw new ServiceCreationException(sprintf('Method %s() is not callable.', Callback::toString([(string) $targetType, $this->name])), 0, $e);
		}

		$refClass = $reflection instanceof \ReflectionMethod
			? $reflection->getDeclaringClass()
			: null;
		if ($refClass && (!$reflection->isPublic()
			|| ($refClass->isTrait() && !$reflection->isStatic())
		)) {
			throw new ServiceCreationException(sprintf('Method %s() is not callable.', Callback::toString([(string) $targetType, $this->name])));
		}

		$resolver->addDependency($reflection);

		return ($type = Nette\Utils\Type::fromReflection($reflection)) && !in_array($type->getSingleName(), ['object', 'mixed'], strict: true)
			? Nette\DI\Helpers::ensureClassType(
				$type,
				sprintf('return type of %s()', Callback::toString([(string) $targetType, $this->name])),
				allowNullable: true,
			)
			: null;
	}


	public function complete(Resolver $resolver): self
	{
		if (!preg_match('#^(\\\?' . Php\Helpers::ReIdentifier . ')+$#D', $this->name)) {
			throw new ServiceCreationException(sprintf(
				"Expected function, method or property name, '%s' given.",
				$this->name,
			));
		}

		// complete any nested expression target, including a Reference (self-contained,
		// so the node does not rely on Statement::normalizeEntity having done it)
		$target = $this->target instanceof Expression
			? $this->target->complete($resolver)
			: $this->target;

		$reflection = null;
		if ($target === null) { // global function
			if (!function_exists($this->name)) {
				throw new ServiceCreationException(sprintf("Function %s doesn't exist.", $this->name));
			}

			$reflection = new \ReflectionFunction($this->name);

		} elseif ($type = $this->resolveTargetType($resolver, $target)) {
			$rc = new \ReflectionClass($type);
			if ($rc->hasMethod($this->name)) {
				$reflection = $rc->getMethod($this->name);
				if (!$reflection->isPublic()) {
					throw new ServiceCreationException(sprintf('%s::%s() is not callable.', $type, $this->name));
				}
			}
		}

		$arguments = $this->arguments;
		if ($reflection) {
			$arguments = $resolver->autowireArguments($reflection, $arguments, $resolver);
			$resolver->addDependency($reflection);
		}

		$arguments = $resolver->resolveArguments($arguments, $this->usedIn($target));
		return new self($target, $this->name, $arguments);
	}


	public function generateCode(PhpGenerator $generator): string
	{
		if ($this->target instanceof Reference) {
			return $generator->formatPhp('?->?(...?:)', [$this->target, $this->name, $this->arguments]);

		} elseif ($this->target instanceof Expression) { // method call on the result of expression
			$inner = $this->target->generateCode($generator);
			if (str_starts_with($inner, 'new ')) {
				$inner = "($inner)";
			}

			return $generator->formatPhp('?->?(...?:)', [new Php\Literal($inner), $this->name, $this->arguments]);
		}

		return $this->target === null
			? $generator->formatPhp('?(...?:)', [new Php\Literal($this->name), $this->arguments])
			: $generator->formatPhp('?::?(...?:)', [new Php\Literal($this->target), $this->name, $this->arguments]);
	}


	public function transformValues(callable $cb): static
	{
		$name = $cb($this->name);
		return new self(
			$cb($this->target),
			is_string($name) ? $name : $this->name,
			$cb($this->arguments),
		);
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


	private function resolveTargetType(Resolver $resolver, Expression|string $target): ?string
	{
		if ($target instanceof Expression) {
			return $target->resolveType($resolver);
		}

		if (!class_exists($target)) {
			throw new ServiceCreationException(sprintf(
				interface_exists($target)
					? "Interface %s can not be used as 'create' or 'factory', did you mean 'implement'?"
					: "Class '%s' not found.",
				$target,
			));
		}

		return $target;
	}


	/**
	 * Legacy Statement-shaped entity, for code inspecting completed definitions.
	 * @return array{Expression|string, string}
	 */
	public function getEntity(): array
	{
		return [$this->target ?? '', $this->name];
	}
}
