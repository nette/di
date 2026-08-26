<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Container::removeService() removes instances as well as factories
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$container = new DI\Container;
$container->addService('instance', new stdClass);
$container->addService('factory', fn(): stdClass => new stdClass);
Assert::true($container->hasService('instance'));
Assert::true($container->hasService('factory'));

Assert::exception(
	fn() => $container->addService('factory', fn(): stdClass => new stdClass),
	Nette\InvalidStateException::class,
	"Service 'factory' already exists.",
);

$container->removeService('instance');
$container->removeService('factory');
Assert::false($container->hasService('instance'));
Assert::false($container->hasService('factory'));

$container->addService('factory', fn(): stdClass => new stdClass);
Assert::type(stdClass::class, $container->getService('factory'));
