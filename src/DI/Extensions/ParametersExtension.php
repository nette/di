<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Extensions;

use Nette;
use Nette\DI\Attributes\Hook;
use Nette\DI\Compiler\DynamicParameter;
use Nette\DI\ContainerBuilder;
use Nette\DI\Helpers;
use Nette\DI\Phase;


/**
 * Processes container parameters and injects them into the generated container.
 */
final class ParametersExtension extends Nette\DI\CompilerExtension
{
	/** @var string[] names of dynamic parameters set by Compiler */
	public array $dynamicParams = [];

	/** @var list<array{DynamicParameter, string, list<int|string>}> */
	public array $dynamicValidators = [];

	/** @var array<string, mixed> */
	protected $config = [];

	/** @var array<string, bool> collected dynamic params (including those containing DynamicParameter) */
	private array $collectedDynamicParams = [];


	public function __construct(
		/** @var array<string, array<mixed[]>> */
		private array &$compilerConfig,
	) {
	}


	#[Hook(Phase::Setup, before: '*')]
	public function doExpandParameters(ContainerBuilder $builder): void
	{
		$params = $this->config;
		foreach ($this->dynamicParams as $key) {
			$params[$key] = new DynamicParameter('$this->getParameter(' . var_export($key, return: true) . ')');
		}

		$builder->parameters = Helpers::expand($params, $params, recursive: true);
		$this->compilerConfig = Helpers::expand($this->compilerConfig, $builder->parameters);
	}


	#[Hook(Phase::Compile)]
	public function doGenerateStaticParameters(Nette\PhpGenerator\ClassType $class): void
	{
		$builder = $this->getContainerBuilder();
		$this->collectedDynamicParams = $this->collectDynamicParams($builder);

		$manipulator = new Nette\PhpGenerator\ClassManipulator($class);
		$manipulator->inheritMethod('getStaticParameters')
			->addBody('return ?;', [array_diff_key($builder->parameters, $this->collectedDynamicParams)]);
	}


	#[Hook(Phase::Compile)]
	public function doGenerateDynamicParameters(Nette\PhpGenerator\ClassType $class): void
	{
		if (!$this->collectedDynamicParams) {
			return;
		}

		$builder = $this->getContainerBuilder();
		$resolver = new Nette\DI\Compiler\Resolver($builder);
		$generator = new Nette\DI\Compiler\PhpGenerator($builder);
		$manipulator = new Nette\PhpGenerator\ClassManipulator($class);

		$method = $manipulator->inheritMethod('getDynamicParameter');
		$method->addBody('return match($key) {');
		foreach ($this->collectedDynamicParams as $key => $foo) {
			$value = Helpers::expand($this->config[$key] ?? null, $builder->parameters);
			try {
				$value = $generator->convertArguments($resolver->completeArguments(Helpers::filterArguments([$value])))[0];
				$method->addBody("\t? => ?,", [$key, $value]);
			} catch (Nette\DI\ServiceCreationException $e) {
				$method->addBody("\t? => throw new Nette\\DI\\ServiceCreationException(?),", [$key, $e->getMessage()]);
			}
		}
		$method->addBody("\tdefault => parent::getDynamicParameter(\$key),\n};");

		if ($preload = array_keys($this->collectedDynamicParams, filter_value: true, strict: true)) {
			$method = $manipulator->inheritMethod('getParameters');
			$method->addBody('array_map($this->getParameter(...), ?);', [$preload]);
			$method->addBody('return parent::getParameters();');
		}
	}


	#[Hook(Phase::Compile)]
	public function doAddDynamicValidators(Nette\PhpGenerator\ClassType $class): void
	{
		foreach ($this->dynamicValidators as [$param, $expected, $path]) {
			if ($param instanceof DynamicParameter) {
				$this->onStartup(
					'Nette\Utils\Validators::assert(?, ?, ?);',
					[$param, $expected, "dynamic parameter used in '" . implode("\u{a0}›\u{a0}", $path) . "'"],
				);
			}
		}
	}


	/** @return array<string, bool> */
	private function collectDynamicParams(ContainerBuilder $builder): array
	{
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
		return $dynamicParams;
	}
}
