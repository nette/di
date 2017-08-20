<?php
declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Service
{
}

class_alias('Service', 'Alias');


$builder = new DI\ContainerBuilder;
$one = $builder->addDefinition('one')
	->setType('Service');
$two = $builder->addDefinition('two')
	->setType('Alias');

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
