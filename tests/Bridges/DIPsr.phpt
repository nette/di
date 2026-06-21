<?php declare(strict_types=1);

/**
 * Test: Nette\Bridges\DIPsr\PsrContainer
 */

use Nette\Bridges\DIPsr\ContainerException;
use Nette\Bridges\DIPsr\NotFoundException;
use Nette\Bridges\DIPsr\PsrContainer;
use Nette\DI;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Service extends stdClass
{
}

class Ambiguous extends stdClass
{
}

class Hidden extends stdClass
{
}

class Preferred extends stdClass
{
}


$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setType(Service::class);
$builder->addAlias('alias', 'one');
$builder->addDefinition('hiddenService') // single service of its type, excluded from autowiring
	->setType(Hidden::class)
	->setAutowired(false);
$builder->addDefinition('amb1')
	->setType(Ambiguous::class);
$builder->addDefinition('amb2')
	->setType(Ambiguous::class);
$builder->addDefinition('pref') // preferred over the plain one below
	->setType(Preferred::class)
	->setAutowired(Preferred::class);
$builder->addDefinition('plain')
	->setType(Preferred::class);

$container = createContainer($builder);
$psr = new PsrContainer($container);


test('resolves by service name', function () use ($psr, $container) {
	Assert::true($psr->has('one'));
	Assert::same($container->getService('one'), $psr->get('one'));
});

test('resolves by alias', function () use ($psr, $container) {
	Assert::true($psr->has('alias'));
	Assert::same($container->getService('one'), $psr->get('alias'));
});

test('resolves by type', function () use ($psr, $container) {
	Assert::true($psr->has(Service::class));
	Assert::same($container->getByType(Service::class), $psr->get(Service::class));
});

test('a non-autowired service is reachable only by name, not by type', function () use ($psr, $container) {
	// by type → not found (mirrors getByType(), which excludes non-autowired services)
	Assert::false($psr->has(Hidden::class));
	$e = Assert::exception(
		fn() => $psr->get(Hidden::class),
		NotFoundException::class,
	);
	Assert::type(NotFoundExceptionInterface::class, $e);

	// by its literal name → resolved
	Assert::true($psr->has('hiddenService'));
	Assert::same($container->getService('hiddenService'), $psr->get('hiddenService'));
});

test('unknown identifier throws NotFoundException', function () use ($psr) {
	Assert::false($psr->has('missing'));
	Assert::false($psr->has(DateTime::class));
	$e = Assert::exception(
		fn() => $psr->get('missing'),
		NotFoundException::class,
		"Service 'missing' not found.",
	);
	Assert::type(NotFoundExceptionInterface::class, $e);
});

test('ambiguous type is reported as not found', function () use ($psr) {
	Assert::false($psr->has(Ambiguous::class));
	$e = Assert::exception(
		fn() => $psr->get(Ambiguous::class),
		NotFoundException::class,
		'Multiple services of type Ambiguous found: amb1, amb2.',
	);
	// NotFoundExceptionInterface extends ContainerExceptionInterface, so both catchers work
	Assert::type(NotFoundExceptionInterface::class, $e);
	Assert::type(ContainerExceptionInterface::class, $e);
});

test('honours autowiring preference among multiple services of a type', function () use ($psr, $container) {
	Assert::true($psr->has(Preferred::class));
	Assert::same($container->getService('pref'), $psr->get(Preferred::class));
});

test('type identifier is normalized', function () use ($psr, $container) {
	Assert::true($psr->has('\Service'));
	Assert::same($container->getService('one'), $psr->get('\Service'));
	Assert::same($container->getService('one'), $psr->get('SERVICE'));
});

test('failure while building the service surfaces as ContainerException', function () use ($psr, $container) {
	$container->addService('broken', fn(): stdClass => $container->getService('broken'));
	Assert::true($psr->has('broken'));
	$e = Assert::exception(
		fn() => $psr->get('broken'),
		ContainerException::class,
		'Circular reference detected for: broken.',
	);
	Assert::type(ContainerExceptionInterface::class, $e);
	Assert::false($e instanceof NotFoundExceptionInterface);
	Assert::type(Nette\InvalidStateException::class, $e->getPrevious());
});
