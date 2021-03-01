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



Assert::exception(function () use ($builder) {
	$builder->getByType('Service');
}, Nette\DI\ServiceCreationException::class, 'Multiple services of type Service found: one, two');

Assert::exception(function () use ($builder) {
	$builder->getByType('Alias');
}, Nette\DI\ServiceCreationException::class, 'Multiple services of type Service found: one, two');

Assert::exception(function () use ($builder) {
	$builder->getByType('\service');
}, Nette\DI\ServiceCreationException::class, 'Multiple services of type Service found: one, two');


Assert::same(
	['one' => $builder->getDefinition('one'), 'two' => $builder->getDefinition('two')],
	$builder->findByType('Service'),
);

Assert::same(
	['one' => $builder->getDefinition('one'), 'two' => $builder->getDefinition('two')],
	$builder->findByType('Alias'),
);

Assert::same(
	['one' => $builder->getDefinition('one'), 'two' => $builder->getDefinition('two')],
	$builder->findByType('\service'),
);
