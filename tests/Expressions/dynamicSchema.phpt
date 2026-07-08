<?php declare(strict_types=1);

/**
 * Test: an Expression value in a ->dynamic() schema slot.
 * Since Expression now implements Nette\Schema\DynamicParameter (so that ::foo(...) etc.
 * pass extension schemas), it also gets recorded as a runtime-validated dynamic value.
 * This locks that such a config compiles and initializes without error.
 */

use Nette\DI;
use Nette\Schema\Expect;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class DynSchemaExtension extends DI\CompilerExtension
{
	public function getConfigSchema(): Nette\Schema\Schema
	{
		return Expect::structure([
			'str' => Expect::string()->dynamic(),
		]);
	}
}


test('a Statement in a dynamic() string slot compiles and initializes', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('dyn', new DynSchemaExtension);
	$container = createContainer($compiler, '
	dyn:
		str: DateTime()::format(\'Y\')
	');

	Assert::noError(fn() => $container->initialize()); // runtime validator sees a string
});


test('a first-class callable in a dynamic() slot compiles and initializes', function () {
	$compiler = new DI\Compiler;
	$ext = new class extends DI\CompilerExtension {
		public function getConfigSchema(): Nette\Schema\Schema
		{
			return Expect::structure([
				'cb' => Expect::type('callable')->dynamic(),
			]);
		}
	};
	$compiler->addExtension('dyn', $ext);
	$container = createContainer($compiler, '
	dyn:
		cb: ::trim(...)
	');

	Assert::noError(fn() => $container->initialize());
});
