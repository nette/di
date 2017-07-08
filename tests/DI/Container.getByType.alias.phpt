<?php

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Service
{
}

class_alias('Service', 'Alias');


$builder = new DI\ContainerBuilder;
$one = $builder->addDefinition('one')
	->setClass('Service');
$two = $builder->addDefinition('two')
	->setClass('Alias');

$container = createContainer($builder);


Assert::exception(function () use ($container) {
	$container->getByType('Service');
}, Nette\DI\MissingServiceException::class, 'Multiple services of type Service found: one, two.');

Assert::exception(function () use ($container) {
	$container->getByType('Alias');
}, Nette\DI\MissingServiceException::class, 'Multiple services of type Service found: one, two.');

Assert::exception(function () use ($builder) {
	$builder->getByType('\service');
}, Nette\DI\ServiceCreationException::class, 'Multiple services of type Service found: one, two');



Assert::same(['one', 'two'], $container->findByType('Service'));
Assert::same(['one', 'two'], $container->findByType('Alias'));
Assert::same(['one', 'two'], $container->findByType('\service'));
