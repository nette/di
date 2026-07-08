<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI;

use Nette\DI\Definitions\AccessorDefinition;
use Nette\DI\Definitions\FactoryDefinition;
use Nette\DI\Definitions\ImportedDefinition;
use Nette\DI\Expressions\ArgumentPlaceholder;
use Nette\DI\Expressions\Call;
use Nette\DI\Expressions\Expansion;
use Nette\DI\Expressions\Instantiation;
use Nette\DI\Expressions\PhpCode;
use Nette\DI\Expressions\Reference;
use Nette\DI\Expressions\ServiceCollection;
use Nette\DI\Expressions\SpecialFunction;
use Nette\PhpGenerator as Php;
use Nette\Utils\Callback;
use function is_array, is_string, sprintf;


/**
 * Marks an argument position as open, to be autowired according to the declared parameter type
 * (the DSL echo of NEON's `_`): create(Foo::class, [_, $scalar]).
 */
const _ = ArgumentPlaceholder::Single;


/**
 * Reference to a service by name. When the string contains a backslash it is treated as a
 * type reference instead (a service name never contains one), just like wire().
 */
function service(string $name): Reference
{
	return new Reference($name);
}


/**
 * Reference to a service by type (autowiring). To autowire a whole argument position by its
 * declared type, use the `_` placeholder instead.
 */
function wire(string $type): Reference
{
	return Reference::fromType($type);
}


/**
 * Collects arguments - positional and/or named - into an array for the args parameter of
 * create()/call()/method()/setup(). Keeps the name: syntax: args(dsn: $x) === ['dsn' => $x].
 * @return array<mixed>
 */
function args(mixed ...$args): array
{
	return $args;
}


/**
 * Reference to the service currently being defined (the NEON @self).
 */
function self(): Reference
{
	return new Reference(Reference::Self);
}


/**
 * The whole value of a parameter, resolved when the container is built.
 */
function param(string $name): Expansion
{
	return new Expansion('%' . $name . '%');
}


/**
 * Explicit interpolation of parameters into a string, e.g. expand('%tempDir%/cache').
 */
function expand(string $template): Expansion
{
	return new Expansion($template);
}


/**
 * Creates a service instance: new Class(...args). Instantiation only - it never calls a method,
 * so create(Foo::class, $x) is unambiguously new Foo($x). For a factory method use call()
 * (static / first-class callable / global function) or service('x')->method('m').
 * @param  class-string  $class
 * @param  array<mixed>  $args
 */
function create(string $class, array $args = []): Instantiation
{
	if (str_contains($class, '::')) {
		throw new \InvalidArgumentException(sprintf(
			"create() only instantiates a class; for the factory method '%s' use call('%s') instead.",
			$class,
			$class,
		));
	}

	return new Instantiation($class, $args);
}


/**
 * Call of a global function, a static method or a first-class callable, evaluated to a value -
 * usable as an argument value or, via add(), as a service creator (the service is the result).
 * @param  string|array{string, string}|(\Closure(mixed...): mixed)  $callable
 * @param  array<mixed>  $args
 */
function call(string|array|\Closure $callable, array $args = []): Call
{
	[$target, $name] = classifyCallable($callable);
	return new Call($target, $name, $args);
}


/**
 * Array of services matching a type (= NEON typed()), a tag (= tagged()), or both at once -
 * passing type: and tag: together yields their intersection.
 */
function services(?string $type = null, ?string $tag = null): ServiceCollection
{
	if ($type === null && $tag === null) {
		throw new \InvalidArgumentException('Pass type: and/or tag: to services().');
	}

	return new ServiceCollection(
		types: $type !== null ? [$type] : [],
		tags: $tag !== null ? [$tag] : [],
	);
}


/**
 * Lossless type conversion of a value; $type is one of 'int', 'bool', 'float' or 'string'.
 */
function cast(string $type, mixed $value): SpecialFunction
{
	return new SpecialFunction($type, [$value]);
}


/**
 * Boolean negation of a value.
 */
function not(mixed $value): SpecialFunction
{
	return new SpecialFunction('not', [$value]);
}


/**
 * Raw PHP code with ? placeholders substituted by arguments; the escape hatch.
 * @param  array<mixed>  $args
 */
function code(string $php, array $args = []): PhpCode
{
	return new PhpCode($php, $args);
}


/**
 * Config-value recognizer: a '@service'/'@\Type' string or a Reference gives a reference, else null.
 * Compose recognizers with ?? to declare per slot which forms are accepted: serviceFrom() ?? classFrom() ?? $v.
 */
function serviceFrom(mixed $value): ?Reference
{
	if ($value instanceof Reference) {
		return $value;
	}

	return is_string($value) && str_starts_with($value, '@') && !str_starts_with($value, '@@')
		? service(substr($value, 1))
		: null;
}


/**
 * Config-value recognizer: a valid class name (validity, not existence) or an Instantiation gives
 * an instantiation, else null. See serviceFrom() for the compose-with-?? pattern.
 * In slots where a bare word may mean something else than a class (a path, a preset name),
 * pass qualified: true - only a backslash-qualified name is then taken as a class, and the user
 * forces class interpretation of a bare name explicitly with a leading backslash ('\MyMapper').
 */
function classFrom(mixed $value, bool $qualified = false): ?Instantiation
{
	if ($value instanceof Instantiation) {
		return $value;
	} elseif (
		is_string($value)
		&& Php\Helpers::isNamespaceIdentifier($value, allowLeadingSlash: true)
		&& (!$qualified || str_contains($value, '\\'))
	) {
		/** @var class-string $value */
		return create($value);
	}

	return null;
}


/**
 * A service supplied from the outside at runtime (= NEON imported/dynamic).
 * @param  class-string  $type
 */
function imported(string $type): ImportedDefinition
{
	return (new ImportedDefinition)->setType($type);
}


/**
 * A generated factory backed by an interface with a single create(): X method. Optionally takes
 * what the factory creates: a class name or a create()/call() expression (mirrors NEON, where
 * create: on a factory definition configures the produced service).
 * @param  class-string  $interface
 * @param  class-string|Expression|null  $creates
 */
function factory(string $interface, string|Expression|null $creates = null): FactoryDefinition
{
	$def = (new FactoryDefinition)->setImplement($interface);
	if ($creates !== null) {
		$def->setCreator(is_string($creates) ? create($creates) : $creates);
	}

	return $def;
}


/**
 * A generated accessor backed by an interface with a single get(): X method. Optionally points
 * to a specific service (from service()/wire()); otherwise it is resolved by the return type.
 * @param  class-string  $interface
 */
function accessor(string $interface, ?Reference $service = null): AccessorDefinition
{
	$def = (new AccessorDefinition)->setImplement($interface);
	if ($service !== null) {
		$def->setReference($service);
	}

	return $def;
}


/**
 * Classifies a callable into [target, method]; target is null for a global function,
 * a class name for a static call, or an expression for a method call.
 * @param  string|array{string, string}|(\Closure(mixed...): mixed)  $callable
 * @return array{Expression|string|null, string}
 * @internal
 */
function classifyCallable(string|array|\Closure $callable): array
{
	/** @var array{string|object, string}|string $unwrapped */
	$unwrapped = $callable instanceof \Closure ? Callback::unwrap($callable) : $callable;

	if (is_array($unwrapped)) {
		[$target, $name] = $unwrapped;
		if (!is_string($target)) {
			throw new \InvalidArgumentException('Cannot use a bound method of an object instance as a service creator.');
		}

		return [$target, $name];
	}

	if (str_contains($unwrapped, '::')) {
		[$target, $name] = explode('::', $unwrapped, 2);
		return [$target, $name];
	}

	return [null, $unwrapped];
}
