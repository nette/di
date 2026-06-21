<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Container::findAutowired()
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Service extends stdClass
{
}

class Child extends Service
{
}

class Service2 extends stdClass
{
}

class Preferred extends stdClass
{
}


$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setType(Service::class);
$builder->addDefinition('child')
	->setType(Child::class)
	->setAutowired(false);
$builder->addDefinition('two')
	->setType(Service2::class);
$builder->addDefinition('three')
	->setType(Service2::class)
	->setAutowired(false);
$builder->addDefinition('pref')
	->setType(Preferred::class)
	->setAutowired(Preferred::class); // preferred over the plain one below
$builder->addDefinition('plain')
	->setType(Preferred::class);

$container = createContainer($builder);


// single autowired service of the type
Assert::same(['one'], $container->findAutowired(Service::class));
Assert::same(['one'], $container->findAutowired(Service::class, preferredOnly: true));

// the only service of its type, but excluded from autowiring
Assert::same([], $container->findAutowired(Child::class));
Assert::same([], $container->findAutowired(Child::class, preferredOnly: true));

// one autowired ('two'), one not ('three')
Assert::same(['two'], $container->findAutowired(Service2::class));
Assert::same(['two'], $container->findAutowired(Service2::class, preferredOnly: true));

// autowiring preference: 'pref' is the only candidate, 'plain' is demoted to lower priority
Assert::same(['pref', 'plain'], $container->findAutowired(Preferred::class));
Assert::same(['pref'], $container->findAutowired(Preferred::class, preferredOnly: true));

// no service of the type
Assert::same([], $container->findAutowired('unknown'));
Assert::same([], $container->findAutowired('unknown', preferredOnly: true));

// several candidates — 'pref' is demoted for stdClass (autowired only for Preferred)
Assert::same(['one', 'two', 'plain', 'pref'], $container->findAutowired(stdClass::class));
Assert::same(['one', 'two', 'plain'], $container->findAutowired(stdClass::class, preferredOnly: true));
