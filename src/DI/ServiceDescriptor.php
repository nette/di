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
	/**
	 * @param  array<string, mixed>  $tags  tag name => tag value
	 * @param  list<string>  $aliases  alias names pointing to this service
	 * @param  ?bool  $autowired  true = yes, false = no, null = unknown (no autowiring metadata)
	 */
	public function __construct(
		public readonly string $name,
		public readonly ?string $type,
		public readonly ?bool $autowired,
		public readonly array $tags,
		public readonly array $aliases,
		public readonly ?object $instance,
	) {
	}
}
