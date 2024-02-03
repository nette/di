<?php

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Service
{
}

class_alias(Service::class, 'Alias');


$builder = new DI\ContainerBuilder;
$one = $builder->addDefinition('one')
	->setType(Service::class);
$two = $builder->addDefinition('two')
	->setType(Alias::class);



Assert::exception(
	fn() => $builder->getByType(Service::class),
	Nette\DI\ServiceCreationException::class,
	'Multiple services of type Service found: one, two',
);

Assert::exception(
	fn() => $builder->getByType(Alias::class),
	Nette\DI\ServiceCreationException::class,
	'Multiple services of type Service found: one, two',
);

Assert::exception(
	fn() => $builder->getByType('\service'),
	Nette\DI\ServiceCreationException::class,
	'Multiple services of type Service found: one, two',
);


Assert::same(
	['one' => $builder->getDefinition('one'), 'two' => $builder->getDefinition('two')],
	$builder->findByType(Service::class),
);

Assert::same(
	['one' => $builder->getDefinition('one'), 'two' => $builder->getDefinition('two')],
	$builder->findByType(Alias::class),
);

Assert::same(
	['one' => $builder->getDefinition('one'), 'two' => $builder->getDefinition('two')],
	$builder->findByType('\service'),
);
