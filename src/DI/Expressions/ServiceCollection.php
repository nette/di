<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Expressions;

use Nette\DI\Compiler\PhpGenerator;
use Nette\DI\Compiler\Resolver;
use Nette\DI\Expression;
use function array_intersect, array_keys, array_map, array_merge, array_unique, array_values;


/**
 * Array of all services matching a criterion.
 */
final class ServiceCollection extends Expression
{
	public function __construct(
		/** @var list<string> */
		public readonly array $types = [],
		/** @var list<string> */
		public readonly array $tags = [],
		/** @var list<Reference>|null resolved references, filled by complete() */
		public readonly ?array $references = null,
	) {
	}


	public function complete(Resolver $resolver): static
	{
		$builder = $resolver->getContainerBuilder();
		$current = $resolver->getCurrentService()?->getName(throw: false);

		// union of names within each dimension, intersection across the dimensions given
		$byType = $this->types
			? array_merge(...array_map(fn($t) => array_keys($builder->findAutowired($t)), $this->types))
			: null;
		$byTag = $this->tags
			? array_merge(...array_map(fn($t) => array_keys($builder->findByTag($t)), $this->tags))
			: null;

		$names = match (true) {
			$byType !== null && $byTag !== null => array_intersect($byType, $byTag),
			$byType !== null => $byType,
			default => $byTag ?? [],
		};

		$references = [];
		foreach (array_unique($names) as $name) {
			if ($name !== $current) {
				$references[$name] = (new Reference($name))->complete($resolver);
			}
		}

		return new self($this->types, $this->tags, array_values($references));
	}


	public function generateCode(PhpGenerator $generator): string
	{
		return $generator->formatPhp('?', [$this->references ?? []]);
	}


	public function transformValues(callable $cb): static
	{
		return new self($cb($this->types), $cb($this->tags), $this->references);
	}
}
