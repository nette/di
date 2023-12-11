<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Extensions;

use Nette;
use Nette\DI\Container;
use Nette\DI\DynamicParameter;
use Nette\DI\Helpers;
use Nette\PhpGenerator\Method;


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

		// expand all except 'services'
		$slice = array_diff_key($this->compilerConfig, ['services' => 1]);
		$slice = Helpers::expand($slice, $builder->parameters);
		$this->compilerConfig = $slice + $this->compilerConfig;
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

		$method = Method::from([Container::class, 'getStaticParameters'])
			->addBody('return ?;', [array_diff_key($builder->parameters, $dynamicParams)]);
		$class->addMember($method);

		if (!$dynamicParams) {
			return;
		}

		$resolver = new Nette\DI\Resolver($builder);
		$generator = new Nette\DI\PhpGenerator($builder);
		$method = $class->inheritMethod('getDynamicParameter');
		$method->addBody('switch (true) {');
		foreach ($dynamicParams as $key => $foo) {
			$value = Helpers::expand($this->config[$key] ?? null, $builder->parameters);
			try {
				$value = $generator->convertArguments($resolver->completeArguments(Helpers::filterArguments([$value])))[0];
				$method->addBody("\tcase \$key === ?: return ?;", [$key, $value]);
			} catch (Nette\DI\ServiceCreationException $e) {
				$method->addBody("\tcase \$key === ?: throw new Nette\\DI\\ServiceCreationException(?);", [$key, $e->getMessage()]);
			}
		}
		$method->addBody("\tdefault: return parent::getDynamicParameter(\$key);\n};");

		if ($preload = array_keys($dynamicParams, true, true)) {
			$method = $class->inheritMethod('getParameters');
			$method->addBody('array_map([$this, \'getParameter\'], ?);', [$preload]);
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
