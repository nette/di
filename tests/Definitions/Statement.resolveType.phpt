<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Definitions\Statement::resolveType() - the type an entity evaluates to.
 */

use Nette\DI;
use Nette\DI\Definitions\Statement;
use Nette\DI\Expressions\Reference;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class ResTarget
{
	public function typedMethod(): ResDep
	{
		return new ResDep;
	}


	public static function typedStatic(): ResDep
	{
		return new ResDep;
	}


	public function untypedMethod()
	{
	}
}

class ResDep
{
}


function makesResDep(): ResDep
{
	return new ResDep;
}


function resolver(): DI\Resolver
{
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')->setType(ResTarget::class);
	return new DI\Resolver($builder);
}


$r = resolver();

// class name entity -> the class itself
Assert::same(ResTarget::class, (new Statement(ResTarget::class))->resolveType($r));

// static / instance method -> its return type
Assert::same(ResDep::class, (new Statement('ResTarget::typedStatic'))->resolveType($r));
Assert::same(ResDep::class, (new Statement([new Reference('a'), 'typedMethod'], []))->resolveType($r));

// global function -> its return type
Assert::same(ResDep::class, (new Statement(['', 'makesResDep']))->resolveType($r));

// method without a class-type return -> null
Assert::null((new Statement([new Reference('a'), 'untypedMethod'], []))->resolveType($r));

// Reference entity -> the referenced service type
Assert::same(ResTarget::class, (new Statement(new Reference('a')))->resolveType($r));

// interface used as a class -> hint about 'implement'
Assert::exception(
	fn() => (new Statement('Iterator'))->resolveType($r),
	DI\ServiceCreationException::class,
	"Interface Iterator can not be used as 'create' or 'factory', did you mean 'implement'?",
);

// unknown class
Assert::exception(
	fn() => (new Statement('Nonexistent'))->resolveType($r),
	DI\ServiceCreationException::class,
	"Class 'Nonexistent' not found.",
);

// built-in return type (string) is not a class -> error
Assert::exception(
	fn() => (new Statement(['', 'trim']))->resolveType($r),
	DI\ServiceCreationException::class,
	'%A%is expected to not be %A?%built-in%A%',
);
