<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Definitions\Statement::generateCode() - every branch as a pure string assertion.
 */

use Nette\DI;
use Nette\DI\Definitions\Statement;
use Nette\DI\Expressions\Reference;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class GenTarget
{
}


function generator(): DI\PhpGenerator
{
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')->setType(GenTarget::class);
	return new DI\PhpGenerator($builder);
}


$g = generator();

// class instantiation
Assert::same('new GenTarget', (new Statement('GenTarget'))->generateCode($g));
Assert::same('new GenTarget(1, 2)', (new Statement('GenTarget', [1, 2]))->generateCode($g));

// PHP literal
Assert::same("substr('x', 1)", (new Statement('substr(?, ?)', ['x', 1]))->generateCode($g));

// global function call
Assert::same("trim('x')", (new Statement(['', 'trim'], ['x']))->generateCode($g));

// static method call
Assert::same('GenTarget::foo(1)', (new Statement('GenTarget::foo', [1]))->generateCode($g));

// method call on a reference
Assert::same("\$this->getService('a')->foo(1)", (new Statement([new Reference('a'), 'foo'], [1]))->generateCode($g));

// method call on the result of another statement (with parenthesization of new)
Assert::same('(new GenTarget)->foo(1)', (new Statement([new Statement('GenTarget'), 'foo'], [1]))->generateCode($g));

// property getter / setter / appender on a reference
Assert::same("\$this->getService('a')->prop", (new Statement([new Reference('a'), '$prop']))->generateCode($g));
Assert::same("\$this->getService('a')->prop = 5", (new Statement([new Reference('a'), '$prop'], [5]))->generateCode($g));
Assert::same("\$this->getService('a')->items[] = 5", (new Statement([new Reference('a'), '$items[]'], [5]))->generateCode($g));

// static property
Assert::same("'GenTarget'::\$sp = 5", (new Statement(['GenTarget', '$sp'], [5]))->generateCode($g));

// self reference
Assert::same('$service->foo(1)', (new Statement([new Reference(Reference::Self), 'foo'], [1]))->generateCode($g));

// invalid entity throws
Assert::exception(
	fn() => (new Statement(null))->generateCode($g),
	Nette\InvalidStateException::class,
);
