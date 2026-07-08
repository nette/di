<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Expressions;

use Nette\DI\Compiler\PhpGenerator;
use Nette\DI\Compiler\Resolver;
use Nette\DI\Expression;
use Nette\DI\Helpers;
use function is_string;


/**
 * Deferred expansion of a %parameter% template against the container parameters.
 */
final class Expansion extends Expression
{
	public function __construct(
		public readonly string $template,
	) {
	}


	public function complete(Resolver $resolver): Expression
	{
		$value = Helpers::expand($this->template, $resolver->getContainerBuilder()->parameters, recursive: true);
		return $value instanceof Expression
			? $value
			: new PhpCode('?', [$value]);
	}


	public function generateCode(PhpGenerator $generator): string
	{
		throw new \LogicException('Expansion must be completed before generating code.');
	}


	public function transformValues(callable $cb): static
	{
		$template = $cb($this->template);
		return new self(is_string($template) ? $template : $this->template);
	}
}
