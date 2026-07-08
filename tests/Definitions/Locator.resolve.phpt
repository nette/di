<?php declare(strict_types=1);

/**
 * Test: LocatorDefinition
 */

use Nette\DI\Compiler\Resolver;
use Nette\DI\Definitions\LocatorDefinition;
use Nette\DI\Expressions\Reference;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


interface Good1
{
	function get($name);
}

interface Good2
{
	function create($name): stdClass;
}

class Service
{
}


testException('', function () {
	$def = new LocatorDefinition;
	$resolver = new Resolver(new Nette\DI\ContainerBuilder);
	$resolver->resolveDefinition($def);
}, Nette\DI\ServiceCreationException::class, 'Type of service is unknown.');


test('', function () {
	$def = new LocatorDefinition;
	$def->setImplement(Good1::class);
	$def->setReferences(['first' => '@a', 'second' => 'stdClass']);

	$builder = new Nette\DI\ContainerBuilder;
	$builder->addDefinition('a')->setType(stdClass::class);
	$builder->addDefinition('b')->setType(Service::class);

	$resolver = new Resolver($builder);
	$resolver->completeDefinition($def);

	Assert::equal([
		'first' => new Reference('a'),
		'second' => new Reference('a'),
	], $def->getReferences());
});


test('', function () {
	$def = new LocatorDefinition;
	$def->setImplement(Good1::class);
	$def->setTagged('tagName');

	$builder = new Nette\DI\ContainerBuilder;
	$builder->addDefinition('a')->addTag('tagName');
	$builder->addDefinition('b')->addTag('tagName', 'b');
	$builder->addDefinition('c')->addTag('tagName', null);
	$builder->addDefinition('d')->addTag('anotherTagName');

	$resolver = new Resolver($builder);
	$resolver->completeDefinition($def);

	Assert::equal([
		1 => new Reference('a'),
		'b' => new Reference('b'),
	], $def->getReferences());
});
