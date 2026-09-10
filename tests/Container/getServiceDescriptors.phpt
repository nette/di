<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Container::getServiceDescriptors()
 */

use Nette\DI;
use Nette\DI\ServiceDescriptor;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class Service extends stdClass
{
}

class Excluded extends stdClass
{
}

class Runtime extends stdClass
{
}


$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setType(Service::class)
	->addTag('mytag', 'value');
$builder->addDefinition('two')
	->setType(Excluded::class)
	->setAutowired(false);
$builder->addAlias('alias', 'one');

$container = createContainer($builder);


// getServiceDescriptors(): map keyed by canonical name; aliases folded in, not listed separately
$descriptors = $container->getServiceDescriptors();
Assert::type('array', $descriptors);
Assert::contains('one', array_keys($descriptors));
Assert::contains('two', array_keys($descriptors));
Assert::false(array_key_exists('alias', $descriptors));


// autowired service with a tag and an alias, not yet instantiated
$one = $descriptors['one'];
Assert::type(ServiceDescriptor::class, $one);
Assert::same('one', $one->name);
Assert::same(Service::class, $one->type);
Assert::true($one->autowired);                 // yes
Assert::same(['mytag' => 'value'], $one->tags);
Assert::same(['alias'], $one->aliases);
Assert::null($one->instance);


// service excluded from autowiring: known type, but not autowired
$two = $descriptors['two'];
Assert::false($two->autowired);                // no
Assert::same([], $two->tags);
Assert::same([], $two->aliases);


// once created, the live instance is exposed
$container->getService('one');
Assert::type(Service::class, $container->getServiceDescriptors()['one']->instance);


// runtime service whose type has no autowiring metadata: autowired is unknown (null)
$container->addService('dynamic', fn(): Runtime => new Runtime);
$descriptors = $container->getServiceDescriptors();
Assert::same(Runtime::class, $descriptors['dynamic']->type);
Assert::null($descriptors['dynamic']->autowired);   // ?


// runtime service without a declared return type: type is unknown (null)
$container->addService('untyped', fn() => new stdClass);
$untyped = $container->getServiceDescriptors()['untyped'];
Assert::null($untyped->type);
Assert::null($untyped->autowired);


// name starting with an uppercase letter cannot be derived back from the method name
$builder = new DI\ContainerBuilder;
$builder->addDefinition('PDO')
	->setType(Service::class)
	->addTag('mytag', 'value');
$builder->addDefinition('lower')->setType(Runtime::class);
$builder->addAlias('shortcut', 'PDO');
$container = createContainer($builder);
$descriptors = $container->getServiceDescriptors();
Assert::contains('PDO', array_keys($descriptors));
Assert::false(array_key_exists('pDO', $descriptors));

// the whole descriptor is looked up by that name, not just its label
$pdo = $descriptors['PDO'];
Assert::same('PDO', $pdo->name);
Assert::same(Service::class, $pdo->type);
Assert::same(['mytag' => 'value'], $pdo->tags);
Assert::same(['shortcut'], $pdo->aliases);
Assert::true($pdo->autowired);
