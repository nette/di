<?php

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$builder = new DI\ContainerBuilder;
$builder->addDefinition('01')
	->setCreator(stdClass::class);

$builder->addDefinition(null)
	->setCreator(stdClass::class);

$builder->addDefinition(null)
	->setCreator(stdClass::class);


$container = createContainer($builder);

Assert::type(stdClass::class, $container->getService('01'));
Assert::type(stdClass::class, $container->getService('02'));
Assert::type(stdClass::class, $container->getService('03'));



Assert::exception(
	fn() => $builder->addDefinition(''),
	Nette\InvalidArgumentException::class,
);

Assert::exception(
	fn() => $builder->addDefinition('0'),
	Nette\InvalidArgumentException::class,
);

Assert::exception(
	fn() => $builder->addDefinition('1'),
	Nette\InvalidArgumentException::class,
);



Assert::exception(
	fn() => $builder->addDefinition('aa~'),
	Nette\InvalidArgumentException::class,
);
