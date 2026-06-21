<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Bridges\DIPsr;

use Nette;
use Nette\DI\Container;
use Nette\DI\Helpers;
use Psr\Container\ContainerInterface;
use function count, sprintf;


/**
 * Exposes a Nette DI container through the PSR-11 ContainerInterface.
 */
final class PsrContainer implements ContainerInterface
{
	public function __construct(
		private readonly Container $container,
	) {
	}


	public function get(string $id): object
	{
		$names = $this->container->findAutowired($id, preferredOnly: true);
		if (count($names) === 1) {
			$name = $names[0];
		} elseif (count($names) > 1) {
			natsort($names);
			throw new NotFoundException(sprintf('Multiple services of type %s found: %s.', Helpers::normalizeClass($id), implode(', ', $names)));
		} elseif ($this->container->hasService($id)) {
			$name = $id;
		} else {
			throw new NotFoundException("Service '$id' not found.");
		}

		try {
			return $this->container->getService($name);
		} catch (Nette\InvalidStateException $e) {
			throw new ContainerException($e->getMessage(), previous: $e);
		}
	}


	public function has(string $id): bool
	{
		return count($this->container->findAutowired($id, preferredOnly: true)) === 1
			|| $this->container->hasService($id);
	}
}
