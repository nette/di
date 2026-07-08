<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Helpers::filterArguments()
 */

use Nette\DI\Definitions\Statement;
use Nette\DI\Expressions\PartialCall;
use Nette\DI\Expressions\Reference;
use Nette\DI\Helpers;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::same([], Helpers::filterArguments([]));

Assert::equal(
	['a', 'b', new Reference('service')],
	Helpers::filterArguments(['a', 'b', '@service']),
);

Assert::equal(
	[new Statement('class', ['a', new Reference('service')])],
	Helpers::filterArguments([new Statement('class', ['a', '@service'])]),
);

// @service in a first-class callable target is converted to a Reference
Assert::equal(
	[new PartialCall(new Reference('svc'), 'method')],
	Helpers::filterArguments([new PartialCall('@svc', 'method')]),
);

// first-class callable nested inside a statement argument
Assert::equal(
	[new Statement('class', [new PartialCall(new Reference('svc'), 'method')])],
	Helpers::filterArguments([new Statement('class', [new PartialCall('@svc', 'method')])]),
);

// function-form callable (no target) is left untouched
Assert::equal(
	[new PartialCall(null, 'trim')],
	Helpers::filterArguments([new PartialCall(null, 'trim')]),
);
