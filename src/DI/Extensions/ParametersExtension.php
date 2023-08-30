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

		foreach ($this->dynamicParams as $key) {
			$params[$key] = new DynamicParameter('$this->parameters[' . var_export($key, return: true) . ']');
		}

		$builder->parameters = Nette\DI\Helpers::expand($params, $params, recursive: true);
		$this->compilerConfig = Nette\DI\Helpers::expand($this->compilerConfig, $builder->parameters);
	}


	public function afterCompile(Nette\PhpGenerator\ClassType $class)
	{
		$params = $this->getContainerBuilder()->parameters;
		if (!$params && !$this->dynamicValidators) {
			return;
		}

		array_walk_recursive($params, function (&$val): void {
			if ($val instanceof Nette\DI\Definitions\Statement) {
				$val = '* unavailable value *';
			}
		});

		$method = $class->addMethod('setupParameters')
			->setProtected()
			->setReturnType('void');
		$method->addParameter('params')->setType('array');
		$method->addBody(<<<'XX'
			if ($tmp = array_diff(?, array_keys($params))) {
				throw new Nette\InvalidArgumentException('Missing parameters: ' . implode(', ', $tmp));
			}
			XX, [$this->dynamicParams]);
		$method->addBody('$this->parameters = $params;');
		$method->addBody('$this->parameters += ?;', [$params]);

		foreach ($this->dynamicValidators as [$param, $expected]) {
			if (!$param instanceof Nette\DI\Definitions\Statement) {
				$method->addBody('Nette\Utils\Validators::assert(?, ?, ?);', [$param, $expected, 'dynamic parameter']);
			}
		}
	}
}
