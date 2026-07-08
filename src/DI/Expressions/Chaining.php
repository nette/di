<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Expressions;


/**
 * Fluent chaining for expressions whose value is an object: continues the chain with
 * a method call, a property read or a class constant on the type of the value.
 * Deliberately not part of the Expression base class - expressions producing scalars,
 * strings or arrays (casts, parameter expansions, service collections) have nothing
 * to chain on and must not offer it.
 */
trait Chaining
{
	/**
	 * Calls a method on the value of this expression; ->method('foo', [...]) is like ->foo(...).
	 * The result is a value and can be chained further.
	 * @param  array<mixed>  $args
	 */
	public function method(string $name, array $args = []): Call
	{
		return new Call($this, $name, $args);
	}


	/**
	 * Reads a property of the value of this expression; ->property('foo') is like ->foo.
	 */
	public function property(string $name): PropertyAccess
	{
		return new PropertyAccess($this, $name);
	}


	/**
	 * Reads a class constant on the type of the value of this expression; ->constant('FOO') is like ::FOO.
	 */
	public function constant(string $name): ConstantFetch
	{
		return new ConstantFetch($this, $name);
	}
}
