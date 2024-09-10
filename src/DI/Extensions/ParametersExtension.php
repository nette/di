<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Extensions;

use Nette;
use Nette\DI\DynamicParameter;
use Nette\DI\Helpers;


/**
 * Parameters.
 */
final class ParametersExtension extends Nette\DI\CompilerExtension
{
	/** @var string[] */
	public array $dynamicParams = [];

	/** @var string[][] */
	public array $dynamicValidators = [];
	private array $compilerConfig;


	public function __construct(array &$compilerConfig)
	{
		$this->compilerConfig = &$compilerConfig;
	}


	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		$params = $this->config;
		foreach ($this->dynamicParams as $key) {
			$params[$key] = new DynamicParameter('$this->getParameter(' . var_export($key, true) . ')');
		}

		$builder->parameters = Helpers::expand($params, $params, recursive: true);
		$this->compilerConfig = Helpers::expand($this->compilerConfig, $builder->parameters);
	}


	public function afterCompile(Nette\PhpGenerator\ClassType $class): void
	{
		$builder = $this->getContainerBuilder();
		$dynamicParams = array_fill_keys($this->dynamicParams, true);
		foreach ($builder->parameters as $key => $value) {
			$value = [$value];
			array_walk_recursive($value, function ($val) use (&$dynamicParams, $key): void {
				if ($val instanceof DynamicParameter) {
					$dynamicParams[$key] ??= true;
				} elseif ($val instanceof Nette\DI\Definitions\Statement) {
					$dynamicParams[$key] = false;
				}
			});
		}

		$manipulator = new Nette\PhpGenerator\ClassManipulator($class);
		$manipulator->inheritMethod('getStaticParameters')
			->addBody('return ?;', [array_diff_key($builder->parameters, $dynamicParams)]);

		if (!$dynamicParams) {
			return;
		}

		$resolver = new Nette\DI\Resolver($builder);
		$generator = new Nette\DI\PhpGenerator($builder);
		$method = $manipulator->inheritMethod('getDynamicParameter');
		$method->addBody('return match($key) {');
		foreach ($dynamicParams as $key => $foo) {
			$value = Helpers::expand($this->config[$key] ?? null, $builder->parameters);
			try {
				$value = $generator->convertArguments($resolver->completeArguments(Helpers::filterArguments([$value])))[0];
				$method->addBody("\t? => ?,", [$key, $value]);
			} catch (Nette\DI\ServiceCreationException $e) {
				$method->addBody("\t? => throw new Nette\\DI\\ServiceCreationException(?),", [$key, $e->getMessage()]);
			}
		}
		$method->addBody("\tdefault => parent::getDynamicParameter(\$key),\n};");

		if ($preload = array_keys($dynamicParams, true, true)) {
			$method = $manipulator->inheritMethod('getParameters');
			$method->addBody('array_map($this->getParameter(...), ?);', [$preload]);
			$method->addBody('return parent::getParameters();');
		}

		foreach ($this->dynamicValidators as [$param, $expected, $path]) {
			if ($param instanceof DynamicParameter) {
				$this->initialization->addBody(
					'Nette\Utils\Validators::assert(?, ?, ?);',
					[$param, $expected, "dynamic parameter used in '" . implode("\u{a0}›\u{a0}", $path) . "'"],
				);
			}
		}
	}
}
