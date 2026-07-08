<?php declare(strict_types=1);

/**
 * Test: registry of error states of expressions - exact exception types and messages.
 * Phase 1 of the Expression refactoring moves validations into node classes; this table
 * guards that no message gets lost or reworded by accident.
 */

use Nette\DI;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class ErrService
{
	public function __construct($arg = null)
	{
	}
}

abstract class ErrAbstract
{
}

class ErrPrivateCtor
{
	private function __construct()
	{
	}
}

class ErrNoCtor
{
}

class ErrHidden
{
	private function priv()
	{
	}
}

interface ErrInterface
{
}


function compileConfig(string $config): void
{
	$compiler = new DI\Compiler;
	$compiler->addConfig((new DI\Config\Loader)->load(Tester\FileMock::create($config, 'neon')));
	$compiler->compile();
}


$cases = [
	// [config, exception, message]
	["services:\n\t- ErrService(...)\n", DI\ServiceCreationException::class, 'Service of type Closure: Cannot create closure for ErrService(...)'],
	["services:\n\t- ErrService( @missing )\n", DI\ServiceCreationException::class, "Service of type ErrService: Reference to missing service 'missing'. (used in ErrService::__construct())"],
	["services:\n\t- UnknownClass\n", DI\ServiceCreationException::class, "Service (UnknownClass::__construct()): Class 'UnknownClass' not found."],
	["services:\n\t- ErrAbstract\n", DI\ServiceCreationException::class, 'Service of type ErrAbstract: Class ErrAbstract is abstract.'],
	["services:\n\t- ErrPrivateCtor\n", DI\ServiceCreationException::class, 'Service of type ErrPrivateCtor: Class ErrPrivateCtor has private constructor.'],
	["services:\n\t- ErrNoCtor(1)\n", DI\ServiceCreationException::class, 'Service of type ErrNoCtor: Unable to pass arguments, class ErrNoCtor has no constructor.'],
	["services:\n\ta: {create: ErrInterface}\n", DI\ServiceCreationException::class, "Service 'a': Interface ErrInterface can not be used as 'create' or 'factory', did you mean 'implement'?"],
	["services:\n\t- ErrHidden::priv()\n", DI\ServiceCreationException::class, 'Service (ErrHidden::priv()): Method ErrHidden::priv() is not callable.'],
	["services:\n\t- ErrService( not(1, 2) )\n", DI\ServiceCreationException::class, 'Service of type ErrService: Function not() expects 1 parameter, 2 given. (used in ErrService::__construct())'],
	["services:\n\ta:\n\t\tcreate: ErrService\n\t\tsetup:\n\t\t\t- '\$prop[]'\n", DI\ServiceCreationException::class, "Service 'a' (type of ErrService): Missing argument for \$prop[]."],
];

foreach ($cases as [$config, $exception, $message]) {
	Assert::exception(
		fn() => compileConfig($config),
		$exception,
		$message,
	);
}


// foreign Definition object in arguments
Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')->setType(ErrService::class);
	$foreign = (new DI\Definitions\ServiceDefinition)->setType(ErrService::class);
	$foreign->setName('a'); // same name, different instance
	$builder->getDefinition('a')->setCreator(ErrService::class, [new DI\Definitions\Statement($foreign)]);
	$builder->complete();
}, DI\ServiceCreationException::class, "%A?%Service 'a' does not match the expected service.%A?%");
