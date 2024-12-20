<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Definitions;

use Nette;


abstract class Expression
{
	abstract public function resolveType(Nette\DI\Resolver $resolver): ?string;


	abstract public function complete(Nette\DI\Resolver $resolver): void;


	abstract public function generateCode(Nette\DI\PhpGenerator $generator): string;
}
