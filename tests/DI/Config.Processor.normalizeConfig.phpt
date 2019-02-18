<?php

/**
 * Test: Nette\DI\Config\Processor::normalizeConfig()
 */

declare(strict_types=1);

use Nette\DI\Config\Processor;
use Nette\DI\Definitions\Statement;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface IFace
{
}

$processor = new Processor(new Nette\DI\ContainerBuilder);

Assert::same([], $processor->normalizeConfig(null));
Assert::same([], $processor->normalizeConfig([]));
Assert::same([false], $processor->normalizeConfig(false));
Assert::same(['factory' => true], $processor->normalizeConfig(true));
Assert::same(['factory' => 'class'], $processor->normalizeConfig('class'));
Assert::same(['implement' => Iface::class], $processor->normalizeConfig(Iface::class));
Assert::same(['factory' => ['class', 'method']], $processor->normalizeConfig(['class', 'method']));
Assert::same(['factory' => [Iface::class, 'method']], $processor->normalizeConfig([Iface::class, 'method']));

$statement = new Statement(['class', 'method']);
Assert::same(['factory' => $statement], $processor->normalizeConfig($statement));

$statement = new Statement(Iface::class, ['foo']);
Assert::same(['implement' => Iface::class, 'factory' => 'foo'], $processor->normalizeConfig($statement));

$statement = new Statement(Iface::class, ['stdClass', 'stdClass']);
Assert::same(['implement' => Iface::class, 'references' => ['stdClass', 'stdClass']], $processor->normalizeConfig($statement));

$statement = new Statement(Iface::class, ['tagged' => 123]);
Assert::same(['implement' => Iface::class, 'tagged' => 123], $processor->normalizeConfig($statement));


// aliases
Assert::same(['type' => 'val'], $processor->normalizeConfig(['class' => 'val']));
Assert::same(['imported' => 'val'], $processor->normalizeConfig(['dynamic' => 'val']));

Assert::exception(function () use ($processor) {
	$processor->normalizeConfig(['class' => 'val', 'type' => 'val']);
}, Nette\InvalidStateException::class, "Options 'class' and 'type' are aliases, use only 'type'.");
