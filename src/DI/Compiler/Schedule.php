<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Compiler;

use Nette;
use Nette\DI\CompilerExtension;
use Nette\DI\Helpers;
use Nette\DI\Phase;
use function array_map;


/**
 * The compilation schedule: the registry of phase hooks, their before/after ordering, and the
 * phase state (which phase is currently draining, which have already completed). It fails loudly
 * on hooks scheduled where they would never run. Owned by the Compiler and shared with the
 * ContainerBuilder, so a config-level hook() reaches the same registry as an extension one.
 * @phpstan-type HookEntry array{callback: \Closure, extension: CompilerExtension|null, before: string|string[]|null, after: string|string[]|null}
 */
final class Schedule
{
	/** @var array<string, list<HookEntry>> */
	private array $hooks = [];

	private ?Phase $runningPhase = null;

	/** @var array<string, true> phases whose final drain has finished */
	private array $completed = [];

	/** who is currently running (extension name / config source), for the journal to stamp mutations */
	private ?string $currentActor = null;


	/**
	 * @param  string|string[]|null  $before
	 * @param  string|string[]|null  $after
	 */
	public function add(
		Phase $phase,
		\Closure $callback,
		?CompilerExtension $extension = null,
		string|array|null $before = null,
		string|array|null $after = null,
	): void
	{
		if ($phase === $this->runningPhase) {
			throw new Nette\InvalidStateException("Cannot schedule a hook into phase {$phase->name} while it is running (it would be silently dropped); schedule it into a later phase.");
		} elseif (isset($this->completed[$phase->value])) {
			throw new Nette\InvalidStateException("Cannot schedule a hook into phase {$phase->name} that has already run; schedule it into a later phase.");
		} elseif ($phase === Phase::Setup && ($before !== null || $after !== null)) {
			throw new Nette\InvalidStateException('Setup phase ordering is declared via #[Hook] attributes (which order the extensions), not via before/after on a manual hook() call.');
		}

		$this->hooks[$phase->value][] = [
			'callback' => $callback,
			'extension' => $extension,
			'before' => $before,
			'after' => $after,
		];
	}


	/**
	 * Returns the phase's hooks ordered by their before/after constraints and removes them.
	 * @return list<HookEntry>
	 */
	public function drainSorted(Phase $phase): array
	{
		$hooks = $this->hooks[$phase->value] ?? [];
		$this->hooks[$phase->value] = [];
		$items = array_map(fn($hook) => ['owner' => $hook['extension']] + $hook, $hooks);
		return array_map(fn($i) => $hooks[$i], Helpers::sortByConstraints($items));
	}


	/**
	 * Returns and removes the hooks of a single extension (in registration order), keeping the rest.
	 * @return list<HookEntry>
	 */
	public function drainForExtension(Phase $phase, CompilerExtension $extension): array
	{
		$run = $keep = [];
		foreach ($this->hooks[$phase->value] ?? [] as $hook) {
			$hook['extension'] === $extension ? $run[] = $hook : $keep[] = $hook;
		}

		$this->hooks[$phase->value] = $keep;
		return $run;
	}


	public function markRunning(Phase $phase): void
	{
		$this->runningPhase = $phase;
	}


	public function markIdle(): void
	{
		$this->runningPhase = null;
	}


	public function markCompleted(Phase $phase): void
	{
		$this->completed[$phase->value] = true;
	}


	public function getRunningPhase(): ?Phase
	{
		return $this->runningPhase;
	}


	/**
	 * Has the given phase's final drain already finished?
	 */
	public function isCompleted(Phase $phase): bool
	{
		return isset($this->completed[$phase->value]);
	}


	public function setCurrentActor(?string $actor): void
	{
		$this->currentActor = $actor;
	}


	public function getCurrentActor(): ?string
	{
		return $this->currentActor;
	}


	/**
	 * Resets the schedule for a fresh compilation.
	 */
	public function clear(): void
	{
		$this->hooks = $this->completed = [];
		$this->runningPhase = null;
	}
}
