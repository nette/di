<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Compiler::addConfig() by an extension after the parameters are expanded.
 */

use Nette\DI;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class LateConfigExtension extends DI\CompilerExtension
{
	public function __construct(
		private array $lateConfig,
	) {
	}


	public function loadConfiguration(): void
	{
		$this->compiler->addConfig($this->lateConfig);
	}
}


test('parameters are expanded in a configuration added by an extension', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('late', new LateConfigExtension([
		'services' => ['foo' => ['create' => 'ArrayObject', 'arguments' => [['%dir%/x']]]],
	]));
	$container = createContainer($compiler, '
parameters:
	dir: /data
');

	Assert::same(['/data/x'], $container->getService('foo')->getArrayCopy());
});


test('parameters cannot be added by an extension', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('late', new LateConfigExtension(['parameters' => ['foo' => 1]]));

	Assert::exception(
		fn() => createContainer($compiler, ''),
		Nette\InvalidStateException::class,
		"Section 'parameters' cannot be added once the parameters are expanded.",
	);
});
