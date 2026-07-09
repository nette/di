<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Compiler;

use Nette\DI\ContainerBuilder;
use Nette\DI\Definition;
use Nette\DI\Helpers;
use Nette\DI\MissingServiceException;
use Nette\DI\ServiceCreationException;
use function count, is_array, sprintf;


/**
 * Resolves service names by type for autowiring.
 */
class Autowiring
{
	/** @var array<class-string, list<string>>  type => service names */
	private array $highPriority = [];

	/** @var array<class-string, list<string>>  type => service names */
	private array $lowPriority = [];

	/** @var array<class-string, class-string> */
	private array $excludedClasses = [];


	public function __construct(
		private readonly ContainerBuilder $builder,
	) {
	}


	/**
	 * Resolves service name by type.
	 * @param class-string  $type
	 * @return ($throw is true ? string : ?string)
	 * @throws MissingServiceException when not found
	 * @throws ServiceCreationException when multiple found
	 */
	public function getByType(string $type, bool $throw = false): ?string
	{
		$type = Helpers::normalizeClass($type);
		$types = $this->highPriority;
		if (empty($types[$type])) {
			if ($throw) {
				if (!class_exists($type) && !interface_exists($type)) {
					throw new MissingServiceException(sprintf("Service of type '%s' not found. Check the class name because it cannot be found.", $type));
				}

				throw new MissingServiceException(sprintf('Service of type %s not found. Did you add it to configuration file?', $type));
			}

			return null;

		} elseif (count($types[$type]) === 1) {
			return $types[$type][0];

		} else {
			$list = $types[$type];
			natsort($list);
			$hint = count($list) === 2 && ($tmp = str_contains($list[0], '.') xor str_contains($list[1], '.'))
				? '. If you want to overwrite service ' . $list[$tmp ? 0 : 1] . ', give it proper name.'
				: '';
			throw new ServiceCreationException(sprintf(
				"Multiple services of type $type found: %s%s",
				implode(', ', $list),
				$hint,
			));
		}
	}


	/**
	 * Gets the service names and definitions of the specified type.
	 * @param class-string  $type
	 * @return array<string, Definition>  service name => definition
	 */
	public function findByType(string $type): array
	{
		$type = Helpers::normalizeClass($type);
		$definitions = $this->builder->getDefinitions();
		$names = array_merge($this->highPriority[$type] ?? [], $this->lowPriority[$type] ?? []);
		$res = [];
		foreach ($names as $name) {
			$res[$name] = $definitions[$name];
		}

		return $res;
	}


	/**
	 * Excludes classes and their ancestors from autowiring lookup.
	 * @param array<class-string>  $types
	 */
	public function addExcludedClasses(array $types): void
	{
		foreach ($types as $type) {
			if (class_exists($type) || interface_exists($type)) {
				$type = Helpers::normalizeClass($type);
				$this->excludedClasses += class_parents($type) + class_implements($type) + [$type => $type];
			}
		}
	}


	/**
	 * Returns low-priority and high-priority type-to-service-names maps.
	 * @return array{array<class-string, list<string>>, array<class-string, list<string>>}
	 */
	public function getClassList(): array
	{
		return [$this->lowPriority, $this->highPriority];
	}


	/**
	 * Rebuilds the type-to-service-names index from current definitions.
	 */
	public function rebuild(): void
	{
		$this->lowPriority = $this->highPriority = $preferred = [];

		foreach ($this->builder->getDefinitions() as $name => $def) {
			if (!($type = $def->getType())) {
				continue;
			}

			$autowired = $def->getAutowired();
			if (is_array($autowired)) {
				foreach ($autowired as $k => $autowiredType) {
					if ($autowiredType === ContainerBuilder::ThisService) {
						$autowired[$k] = $type;
					} elseif (!is_a($type, $autowiredType, allow_string: true)) {
						throw new ServiceCreationException(sprintf(
							"Incompatible class %s in autowiring definition of service '%s'.",
							$autowiredType,
							$name,
						));
					}
				}
			}

			foreach (class_parents($type) + class_implements($type) + [$type] as $parent) {
				if (!$autowired || isset($this->excludedClasses[$parent])) {
					continue;
				} elseif (is_array($autowired)) {
					$priority = false;
					foreach ($autowired as $autowiredType) {
						if (is_a($parent, $autowiredType, allow_string: true)) {
							if (empty($preferred[$parent]) && isset($this->highPriority[$parent])) {
								$this->lowPriority[$parent] = array_merge($this->lowPriority[$parent] ?? [], $this->highPriority[$parent]);
								$this->highPriority[$parent] = [];
							}

							$preferred[$parent] = $priority = true;
							break;
						}
					}
				} else {
					$priority = empty($preferred[$parent]);
				}

				$list = $priority ? 'highPriority' : 'lowPriority';
				$this->$list[$parent][] = $name;
			}
		}
	}
}
