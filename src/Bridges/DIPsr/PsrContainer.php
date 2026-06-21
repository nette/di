<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Bridges\DIPsr;

use Nette\DI\Container;
use Psr\Container\ContainerInterface;


/**
 * Exposes a Nette DI container through the PSR-11 ContainerInterface.
 *
 * An identifier is resolved as the autowired type (class/interface) of a service, then as a
 * literal service name (or alias) — i.e. exactly what getByType() or getByName() would return.
 * An id that resolves to neither a single autowired service nor a service name is reported as
 * not found; that includes an ambiguous type (several autowired services), which has() likewise
 * treats as absent.
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
			return $this->container->getService($names[0]);
		} elseif (count($names) > 1) {
			natsort($names);
			throw new NotFoundException(sprintf("Multiple services of type '%s' found: %s.", $id, implode(', ', $names)));
		} elseif ($this->container->hasService($id)) {
			return $this->container->getService($id);
		}

		throw new NotFoundException(sprintf("Service '%s' not found.", $id));
	}


	public function has(string $id): bool
	{
		return count($this->container->findAutowired($id, preferredOnly: true)) === 1
			|| $this->container->hasService($id);
	}
}
