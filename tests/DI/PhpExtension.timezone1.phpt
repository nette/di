<?php

/**
 * Test: Nette\DI\Extensions\PhpExtension: test proper timezone translation (specified in PHP extension).
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

// configure test case environment
date_default_timezone_set('UTC');
Assert::equal('UTC', date_default_timezone_get());


$compiler = new DI\Compiler;
$compiler->addExtension('php', new Nette\DI\Extensions\PhpExtension());
$container = createContainer($compiler, '
parameters:
	date: 2016-09-01
	date2: [2016-09-02, 2016-09-03 10:20:00]
	nested:
		param: 2016-02-29 01:02:03
php:
	date.timezone: Europe/Prague
');


$builder = $compiler->getContainerBuilder();

Assert::equal(new DateTime('2016-09-01', new DateTimeZone('Europe/Prague')), $builder->parameters['date']);
Assert::equal(new DateTime('2016-09-03 10:20:00', new DateTimeZone('Europe/Prague')), $builder->parameters['date2'][1]);
Assert::equal(new DateTime('2016-02-29 01:02:03', new DateTimeZone('Europe/Prague')), $builder->parameters['nested']['param']);