<?php

/**
 * Test: Nette\DI\Config\DefinitionSchema::normalize()
 */

declare(strict_types=1);

use Nette\DI\Config\DefinitionSchema;
use Nette\DI\Definitions\Statement;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface IFace
{
}


Assert::with(DefinitionSchema::class, function () {
	$schema = new DefinitionSchema;

	Assert::same([], $schema->normalize(null));
	Assert::same([], $schema->normalize([]));
	Assert::same([false], $schema->normalize(false));
	Assert::same(['factory' => true], $schema->normalize(true));
	Assert::same(['factory' => 'class'], $schema->normalize('class'));
	Assert::same(['implement' => Iface::class], $schema->normalize(Iface::class));
	Assert::same(['factory' => ['class', 'method']], $schema->normalize(['class', 'method']));
	Assert::same(['factory' => [Iface::class, 'method']], $schema->normalize([Iface::class, 'method']));

	$statement = new Statement(['class', 'method']);
	Assert::same(['factory' => $statement], $schema->normalize($statement));

	$statement = new Statement(Iface::class, ['foo']);
	Assert::same(['implement' => Iface::class, 'factory' => 'foo'], $schema->normalize($statement));

	$statement = new Statement(Iface::class, ['stdClass', 'stdClass']);
	Assert::same(['implement' => Iface::class, 'references' => ['stdClass', 'stdClass']], $schema->normalize($statement));

	$statement = new Statement(Iface::class, ['tagged' => 123]);
	Assert::same(['implement' => Iface::class, 'tagged' => 123], $schema->normalize($statement));


	// aliases
	Assert::same(['factory' => 'val'], $schema->normalize(['class' => 'val']));
	Assert::same(['imported' => 'val'], $schema->normalize(['dynamic' => 'val']));

	Assert::exception(function () use ($schema) {
		$schema->normalize(['class' => 'val', 'type' => 'val']);
	}, Nette\DI\InvalidConfigurationException::class, "Options 'class' and 'type' are aliases, use only 'type'.");
});
