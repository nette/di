<?php

/**
 * Test: Nette\DI\Extensions\PhpExtension: test proper timezone translation (specified in PHP extension).
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

// configure test case environment
date_default_timezone_set('America/Los_Angeles');
Assert::equal('America/Los_Angeles', date_default_timezone_get());

class MyClass
{
    private $date;

    public function __construct ($date)
    {
        $this->date = $date;

    }
    public function getDate()
    {
        return $this->date;
    }
}


$compiler = new DI\Compiler;
$compiler->addExtension('php', new Nette\DI\Extensions\PhpExtension());
/** @var DI\Container $container */
$container = createContainer($compiler, '
parameters:
    date: 2016-09-01 10:20:30
services:
    test: MyClass(%date%)
php:
	date.timezone: Europe/Prague
');

$service = $container->getService("test");

Assert::equal(new DateTimeImmutable('2016-09-01 10:20:30', new DateTimeZone('Europe/Prague')), $service->getDate());