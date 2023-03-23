<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Extensions;

use Nette;
use Nette\DI\DynamicParameter;


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


	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$params = $this->config;
		$resolver = new Nette\DI\Resolver($builder);
		$generator = new Nette\DI\PhpGenerator($builder);

		foreach ($this->dynamicParams as $key) {
			$params[$key] = array_key_exists($key, $params)
				? new DynamicParameter($generator->formatPhp('($this->parameters[?] \?\? ?)', $resolver->completeArguments(Nette\DI\Helpers::filterArguments([$key, $params[$key]]))))
				: new DynamicParameter((new Nette\PhpGenerator\Dumper)->format('$this->parameters[?]', $key));
		}

		$builder->parameters = Nette\DI\Helpers::expand($params, $params, recursive: true);
		$this->compilerConfig = Nette\DI\Helpers::expand($this->compilerConfig, $builder->parameters);
	}


	public function afterCompile(Nette\PhpGenerator\ClassType $class)
	{
		$params = $this->getContainerBuilder()->parameters;
		array_walk_recursive($params, function (&$val): void {
			if ($val instanceof Nette\DI\Definitions\Statement) {
				$val = '* unavailable value *';
			}
		});

		$cnstr = $class->getMethod('__construct');
		$cnstr->addBody('$this->parameters += ?;', [$params]);
		foreach ($this->dynamicValidators as [$param, $expected]) {
			if (!$param instanceof Nette\DI\Definitions\Statement) {
				$cnstr->addBody('Nette\Utils\Validators::assert(?, ?, ?);', [$param, $expected, 'dynamic parameter']);
			}
		}
	}
}
