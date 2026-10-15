<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI;


/**
 * Read-only snapshot of a single service in a compiled container, from Container::getServiceDescriptors().
 */
final class ServiceDescriptor
{
	public function __construct(
		public readonly string $name,
		public readonly ?string $type,
		/** true = yes, false = no, null = unknown (no autowiring metadata) */
		public readonly ?bool $autowired,
		/** @var array<string, mixed> tag name => tag value */
		public readonly array $tags,
		/** @var list<string> alias names pointing to this service */
		public readonly array $aliases,
		public readonly ?object $instance,
	) {
	}
}
