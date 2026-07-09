<?php declare(strict_types=1);

/**
 * Test: end-to-end smoke of the Definitions DSL - build a container purely through the
 * programmatic API (add / create / wire / param / service / setup / tag), compile it and
 * verify runtime behaviour. A continuous check that the whole pipeline holds together.
 */

use Nette\DI;
use Nette\DI\ContainerBuilder;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class SmokeDep
{
}

class SmokeLogger
{
	public array $log = [];
}

class SmokeService
{
	public ?SmokeLogger $logger = null;


	public function __construct(
		public SmokeDep $dep,
		public string $name,
	) {
	}


	public function setLogger(SmokeLogger $logger): void
	{
		$this->logger = $logger;
	}
}


$builder = new ContainerBuilder;
$builder->parameters['appName'] = 'demo';

$builder->add('dep', di\create(SmokeDep::class));
$builder->add('logger', di\create(SmokeLogger::class));
$builder->add('svc', di\create(SmokeService::class, [di\_, di\param('appName')]))
	->setup('setLogger', [di\service('logger')])
	->tag('smoke');

$container = createContainer($builder);

$svc = $container->getByType(SmokeService::class);
Assert::type(SmokeService::class, $svc);
Assert::type(SmokeDep::class, $svc->dep);                  // _ autowired position 0
Assert::same('demo', $svc->name);                          // param('appName') resolved
Assert::same($container->getByType(SmokeLogger::class), $svc->logger); // setup + service() ref
Assert::same(['svc'], array_keys($container->findByTag('smoke')));
