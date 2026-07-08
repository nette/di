<?php declare(strict_types=1);

/**
 * Test: CompilerExtension::onStartup() adds code to the container's initialize() method,
 * accepting a DSL expression or a PHP string with args (which may contain DSL expressions) -
 * a full replacement for $this->initialization->addBody().
 */

use Nette\DI;
use Nette\DI\Attributes\Hook;
use Nette\DI\ContainerBuilder;
use Nette\DI\Phase;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class Started
{
	public bool $started = false;

	/** @var string[] */
	public array $tags = [];


	public function start(): void
	{
		$this->started = true;
	}


	public function tag(string $t): void
	{
		$this->tags[] = $t;
	}
}


class StartupExtension extends DI\CompilerExtension
{
	#[Hook(Phase::Register)]
	public function doRegister(ContainerBuilder $builder): void
	{
		$builder->add('svc', di\create(Started::class));
	}


	#[Hook(Phase::Compile)]
	public function doStartup(): void
	{
		// expression form: onStartup supplies the trailing `;`
		$this->onStartup(di\service('svc')->method('start'));
		// string form with a DSL expression argument (completed by formatPhp)
		$this->onStartup('?->tag(?);', [di\service('svc'), 'fromString']);
	}
}


test('onStartup() code runs in the container initialize()', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('app', new StartupExtension);
	$container = createContainer($compiler->setClassName('StartupContainer'));

	$svc = $container->getByType(Started::class);
	Assert::false($svc->started);          // initialize() not called yet by the test helper
	Assert::same([], $svc->tags);

	$container->initialize();

	Assert::true($svc->started);            // expression form ran
	Assert::same(['fromString'], $svc->tags); // string + DSL-arg form ran
});
