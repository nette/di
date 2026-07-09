<?php declare(strict_types=1);

/**
 * Test: ContainerBuilder::add() and the factory()/accessor()/imported() element functions.
 */

use Nette\DI;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\AccessorDefinition;
use Nette\DI\Definitions\FactoryDefinition;
use Nette\DI\Definitions\ImportedDefinition;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Expressions\Instantiation;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class AddSvc
{
	public function __construct(
		public string $x = '',
	) {
	}


	public static function make(): self
	{
		return new self('made');
	}
}

interface AddFactory
{
	public function create(): AddSvc;
}

interface AddAccessor
{
	public function get(): AddSvc;
}


test('add() from an expression creator returns a fluent ServiceDefinition', function () {
	$b = new ContainerBuilder;
	$def = $b->add('svc', di\create(AddSvc::class, ['hello']));
	Assert::type(ServiceDefinition::class, $def);
	Assert::type(Instantiation::class, $def->getCreator());
	Assert::same('svc', $def->getName());
	// add() returns the same object as get()
	Assert::same($def, $b->get('svc'));
});


test('add() from a bare class name', function () {
	$b = new ContainerBuilder;
	$def = $b->add('svc', AddSvc::class);
	Assert::type(ServiceDefinition::class, $def);
});


test('add() from a factory via call() - the service is the call result', function () {
	$b = new ContainerBuilder;
	$b->add('svc', di\call([AddSvc::class, 'make']));

	$container = createContainer($b);
	$svc = $container->getByType(AddSvc::class);
	Assert::type(AddSvc::class, $svc);
	Assert::same('made', $svc->x);
});


test('add() with a duplicate name throws', function () {
	$b = new ContainerBuilder;
	$b->add('svc', AddSvc::class);
	Assert::exception(fn() => $b->add('svc', AddSvc::class), Nette\InvalidStateException::class);
});


test('add() accepts a ready-made definition from factory()', function () {
	$b = new ContainerBuilder;
	$def = $b->add('factory', di\factory(AddFactory::class));
	Assert::type(FactoryDefinition::class, $def);
	Assert::same('factory', $def->getName());
});


test('imported() builds an ImportedDefinition', function () {
	$def = di\imported(AddSvc::class);
	Assert::type(ImportedDefinition::class, $def);
	Assert::same(AddSvc::class, $def->getType());
});


test('factory()/accessor() build typed definitions', function () {
	$factory = di\factory(AddFactory::class, AddSvc::class);
	Assert::type(FactoryDefinition::class, $factory);
	Assert::same(AddFactory::class, $factory->getType());

	$accessor = di\accessor(AddAccessor::class, di\service('svc'));
	Assert::type(AccessorDefinition::class, $accessor);
	Assert::same('svc', $accessor->getReference()->getValue());
});


test('factory() configures the produced service, fluent setup() delegates to the result', function () {
	$b = new ContainerBuilder;
	$b->add('factory', di\factory(AddFactory::class, di\create(AddSvc::class, ['hi'])))
		->setup('make');

	$def = $b->get('factory', of: FactoryDefinition::class);
	Assert::count(1, $def->getResultDefinition()->getSetup());

	$container = createContainer($b);
	Assert::same('hi', $container->getByType(AddFactory::class)->create()->x);
});


test('getName() on an unregistered definition throws, throw: false gives null', function () {
	$def = di\imported(AddSvc::class);
	Assert::exception(
		fn() => $def->getName(),
		Nette\InvalidStateException::class,
		'The definition has no name yet;%a%',
	);
	Assert::null($def->getName(throw: false));

	$b = new ContainerBuilder;
	$registered = $b->add('svc', AddSvc::class);
	Assert::same('svc', $registered->getName(throw: false));
});
