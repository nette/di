<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI;

use Nette\DI\Definitions\ServiceDefinition;


/**
 * The config-facing view of the container: the vocabulary a config file (or an extension) uses to
 * add, address, modify and schedule services. It is the type a PHP config closure receives
 * (`return function (Definitions $di) {...}`) and lives in one namespace with the element functions
 * (service(), create(), ...) so config files can use everything bare. Implemented by ContainerBuilder;
 * the internal compilation machinery (resolve/complete/generateCode) is deliberately not part of it.
 *
 * The whole vocabulary is immediate (ADR definitions-dsl): it works with what exists now. hook() is the only
 * way to defer work to reach services that extensions register later.
 */
interface Definitions
{
	/**
	 * Registers a new service (errors if the name exists); returns its definition for fluent config.
	 * The creator is a class name, an expression or a ready-made definition.
	 * @template TDef of Definition
	 * @param  class-string|Expression|TDef  $creator
	 * @return ($creator is Definition ? TDef : ServiceDefinition)
	 */
	public function add(?string $name, string|Expression|Definition $creator): Definition;

	/**
	 * Removes an existing service by name or type (immediate). To drop a service an extension
	 * registers, use hook(Phase::Discover, ..., after: '*').
	 * @param  class-string|null  $type
	 */
	public function remove(?string $name = null, ?string $type = null): void;

	/**
	 * Gets a single service definition by name or by type, asserting it is of the given definition
	 * class (ServiceDefinition by default). Pass exactly one of name/type.
	 * @template T of Definition
	 * @param  class-string|null  $type
	 * @param  class-string<T>|null  $of
	 * @return ($of is null ? ServiceDefinition : T)
	 */
	public function get(?string $name = null, ?string $type = null, ?string $of = null): Definition;

	/**
	 * Gets all service definitions matching a type or a tag, optionally filtered to a definition class.
	 * @template T of Definition
	 * @param  class-string|null  $type
	 * @param  class-string<T>|null  $of
	 * @return ($of is null ? array<string, Definition> : array<string, T>)
	 */
	public function find(?string $type = null, ?string $tag = null, ?string $of = null): array;

	/**
	 * Does a service of the given name, type or tag exist? Pass exactly one.
	 * @param  class-string|null  $type
	 */
	public function has(?string $name = null, ?string $type = null, ?string $tag = null): bool;

	/**
	 * Gets all service definitions.
	 * @return array<string, Definition>
	 */
	public function getAll(): array;

	/**
	 * Defers a callback into a compilation phase (the only way to reach services registered later);
	 * only Register, Discover and Modify are available. The callback receives these Definitions.
	 * @param  string|string[]|null  $before
	 * @param  string|string[]|null  $after
	 */
	public function hook(
		Phase $phase,
		\Closure $callback,
		string|array|null $before = null,
		string|array|null $after = null,
	): void;
}
