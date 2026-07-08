<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Config;

if (false) {
	/** @deprecated use Nette\DI\Config\Adapter */
	interface IAdapter
	{
	}
} elseif (!interface_exists(IAdapter::class)) {
	class_alias(Adapter::class, IAdapter::class);
}

namespace Nette\DI;

if (false) {
	/** @deprecated use Nette\DI\Compiler\Resolver */
	class Resolver
	{
	}
} elseif (!class_exists(Resolver::class)) {
	class_alias(Compiler\Resolver::class, Resolver::class);
}

if (false) {
	/** @deprecated use Nette\DI\Compiler\Autowiring */
	class Autowiring
	{
	}
} elseif (!class_exists(Autowiring::class)) {
	class_alias(Compiler\Autowiring::class, Autowiring::class);
}

if (false) {
	/** @deprecated use Nette\DI\Compiler\PhpGenerator */
	class PhpGenerator
	{
	}
} elseif (!class_exists(PhpGenerator::class)) {
	class_alias(Compiler\PhpGenerator::class, PhpGenerator::class);
}

if (false) {
	/** @deprecated use Nette\DI\Compiler\DependencyChecker */
	class DependencyChecker
	{
	}
} elseif (!class_exists(DependencyChecker::class)) {
	class_alias(Compiler\DependencyChecker::class, DependencyChecker::class);
}

if (false) {
	/** @deprecated use Nette\DI\Compiler\DynamicParameter */
	class DynamicParameter
	{
	}
} elseif (!class_exists(DynamicParameter::class)) {
	class_alias(Compiler\DynamicParameter::class, DynamicParameter::class);
}

if (false) {
	/** @deprecated use Nette\DI\Definitions\ServiceDefinition */
	class ServiceDefinition
	{
	}
} elseif (!class_exists(ServiceDefinition::class)) {
	class_alias(Definitions\ServiceDefinition::class, ServiceDefinition::class);
}

if (false) {
	/** @deprecated use Nette\DI\Definitions\Statement */
	class Statement
	{
	}
} elseif (!class_exists(Statement::class)) {
	class_alias(Definitions\Statement::class, Statement::class);
}

namespace Nette\DI\Definitions;

use Nette\DI;


if (false) {
	/** @deprecated use Nette\DI\Expressions\Reference */
	class Reference
	{
	}
} elseif (!class_exists(Reference::class)) {
	class_alias(DI\Expressions\Reference::class, Reference::class);
}

if (false) {
	/** @deprecated use Nette\DI\Definition */
	class Definition
	{
	}
} elseif (!class_exists(Definition::class)) {
	class_alias(DI\Definition::class, Definition::class);
}
