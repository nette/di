<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Extensions;

use Nette;


/**
 * Parameters.
 */
final class ParametersExtension extends Nette\DI\CompilerExtension
{
	/** @var string[] */
	public $dynamicParams = [];

	/** @var array */
	private $compilerConfig;


	public function __construct(array &$compilerConfig)
	{
		$this->compilerConfig = &$compilerConfig;
	}


	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$params = $this->config;

		foreach ($this->dynamicParams as $key) {
			$params[$key] = array_key_exists($key, $params)
				? $builder::literal('($this->parameters[?] \?\? ?)', [$key, $params[$key]])
				: $builder::literal('$this->parameters[?]', [$key]);
		}

		$builder->parameters = Nette\DI\Helpers::expand($params, $params, true);

		// expand all except 'services'
		$slice = array_diff_key($this->compilerConfig, ['services' => 1]);
		$this->compilerConfig = Nette\DI\Helpers::expand($slice, $builder->parameters) + $this->compilerConfig;
	}
}
