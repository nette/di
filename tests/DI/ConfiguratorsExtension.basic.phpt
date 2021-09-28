<?php

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class Service
{
}


$compiler = new DI\Compiler;
$compiler->loadConfig(__DIR__ . '/files/phpConfig1.php');
$compiler->loadConfig(__DIR__ . '/files/phpConfig2.php');
$container = createContainer($compiler);

Assert::type(Service::class, $container->getByType(Service::class));
Assert::same(['foo' => 123], $container->parameters);
