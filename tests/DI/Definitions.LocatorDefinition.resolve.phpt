<?php

/**
 * Test: LocatorDefinition
 */

declare(strict_types=1);

use Nette\DI\Definitions\LocatorDefinition;
use Nette\DI\Definitions\Reference;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface Good1
{
	public function get($name);
}

interface Good2
{
	public function create($name): stdClass;
}

class Service
{
}


testException('', function () {
	$def = new LocatorDefinition;
	$resolver = new Nette\DI\Resolver(new Nette\DI\ContainerBuilder);
	$resolver->resolveDefinition($def);
}, Nette\DI\ServiceCreationException::class, 'Type of service is unknown.');


test('', function () {
	$def = new LocatorDefinition;
	$def->setImplement(Good1::class);
	$def->setReferences(['first' => '@a', 'second' => 'stdClass']);

	$builder = new Nette\DI\ContainerBuilder;
	$builder->addDefinition('a')->setType(stdClass::class);
	$builder->addDefinition('b')->setType(Service::class);

	$resolver = new Nette\DI\Resolver($builder);
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

	$resolver = new Nette\DI\Resolver($builder);
	$resolver->completeDefinition($def);

	Assert::equal([
		1 => new Reference('a'),
		'b' => new Reference('b'),
	], $def->getReferences());
});
