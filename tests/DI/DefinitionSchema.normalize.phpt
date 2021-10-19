<?php

/**
 * Test: Nette\DI\Config\DefinitionSchema::normalize()
 */

declare(strict_types=1);

use Nette\DI\Definitions\Statement;
use Nette\DI\Extensions\DefinitionSchema;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface IFace
{
}


Assert::with(DefinitionSchema::class, function () {
	$schema = new DefinitionSchema(new Nette\DI\ContainerBuilder);
	$context = new Nette\Schema\Context;

	Assert::same([], $schema->normalize(null, $context));
	Assert::same([], $schema->normalize([], $context));
	Assert::same([false], $schema->normalize(false, $context));
	Assert::same(['factory' => true], $schema->normalize(true, $context));
	Assert::same(['factory' => 'class'], $schema->normalize('class', $context));
	Assert::same(['implement' => Iface::class], $schema->normalize(Iface::class, $context));
	Assert::same(['factory' => ['class', 'method']], $schema->normalize(['class', 'method'], $context));
	Assert::same(['factory' => [Iface::class, 'method']], $schema->normalize([Iface::class, 'method'], $context));

	$statement = new Statement(['class', 'method']);
	Assert::same(['factory' => $statement], $schema->normalize($statement, $context));

	$statement = new Statement(Iface::class, ['foo']);
	Assert::same(['implement' => Iface::class, 'factory' => 'foo'], $schema->normalize($statement, $context));

	$statement = new Statement(Iface::class, ['stdClass', 'stdClass']);
	Assert::same(['implement' => Iface::class, 'references' => ['stdClass', 'stdClass']], $schema->normalize($statement, $context));

	$statement = new Statement(Iface::class, ['tagged' => 123]);
	Assert::same(['implement' => Iface::class, 'tagged' => 123], $schema->normalize($statement, $context));

	// aliases
	Assert::same(['factory' => 'val'], $schema->normalize(['class' => 'val'], $context));
	Assert::same(['imported' => 'val'], $schema->normalize(['dynamic' => 'val'], $context));

	Assert::exception(function () use ($schema, $context) {
		$schema->normalize(['class' => 'val', 'type' => 'val'], $context);
	}, Nette\DI\InvalidConfigurationException::class, "Options 'class' and 'type' are aliases, use only 'type'.");
});
