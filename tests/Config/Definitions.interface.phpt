<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Definitions - the config-facing interface implemented by ContainerBuilder,
 * exposing only the vocabulary and hiding the internal compilation machinery.
 */

use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


Assert::true(is_a(ContainerBuilder::class, Definitions::class, allow_string: true));


// the interface exposes the config vocabulary...
$methods = get_class_methods(Definitions::class);
sort($methods);
Assert::same(['add', 'find', 'get', 'getAll', 'has', 'hook', 'remove'], $methods);

// ...and deliberately hides the internal compilation machinery and legacy names
foreach (['resolve', 'complete', 'generateCode', 'exportMeta', 'addDefinition', 'removeDefinition', 'getDefinitions'] as $internal) {
	Assert::false(in_array($internal, $methods, true), "$internal must not be on the Definitions interface");
}


// a value typed as the interface works like the builder
function useDefinitions(Definitions $di): void
{
	$def = $di->add('svc', Nette\DI\create(stdClass::class));
	Assert::type(Nette\DI\Definitions\ServiceDefinition::class, $def);
	Assert::true($di->has('svc'));
	Assert::same($def, $di->get('svc'));
}


useDefinitions(new ContainerBuilder);
