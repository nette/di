<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Compiler;

use Nette\DI\Definition;
use function array_filter, array_values;


/**
 * Append-only log of every change made to a service definition during compilation, so that each
 * service has a traceable biography ("db: created here, setup added by TracyExtension, ...").
 * Fed by Definition's mutation primitives, so it catches the new API, the legacy verbs and direct
 * access alike. The actor (which extension / config file made the change) is stamped in later.
 * @phpstan-type Entry array{service: ?string, action: string, value: mixed, actor: ?string}
 */
final class Journal
{
	/** @var list<Entry> */
	private array $entries = [];


	public function record(Definition $definition, string $action, mixed $value = null, ?string $actor = null): void
	{
		$this->entries[] = [
			'service' => $definition->getName(),
			'action' => $action,
			'value' => $value,
			'actor' => $actor,
		];
	}


	/**
	 * Returns who created the given service (the actor of its first recorded entry), or null.
	 */
	public function getCreator(string $service): ?string
	{
		return ($this->getBiography($service)[0] ?? null)['actor'] ?? null;
	}


	/**
	 * Returns all recorded entries in order.
	 * @return list<Entry>
	 */
	public function getEntries(): array
	{
		return $this->entries;
	}


	/**
	 * Returns the biography of a single service - the changes made to it, in order.
	 * @return list<Entry>
	 */
	public function getBiography(string $service): array
	{
		return array_values(array_filter($this->entries, fn($entry): bool => $entry['service'] === $service));
	}
}
