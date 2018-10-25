<?php

/**
 * Test: Nette\DI\Config\Processor::normalizeStructure()
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

Assert::same([], $processor->normalizeStructure(null));
Assert::same([], $processor->normalizeStructure([]));
Assert::same([false], $processor->normalizeStructure(false));
Assert::same(['factory' => true], $processor->normalizeStructure(true));
Assert::same(['factory' => 'class'], $processor->normalizeStructure('class'));
Assert::same(['implement' => Iface::class], $processor->normalizeStructure(Iface::class));
Assert::same(['factory' => ['class', 'method']], $processor->normalizeStructure(['class', 'method']));
Assert::same(['factory' => [Iface::class, 'method']], $processor->normalizeStructure([Iface::class, 'method']));

$statement = new Statement(['class', 'method']);
Assert::same(['factory' => $statement], $processor->normalizeStructure($statement));

$statement = new Statement(Iface::class, ['foo']);
Assert::same(['implement' => Iface::class, 'factory' => 'foo'], $processor->normalizeStructure($statement));

$statement = new Statement(Iface::class, ['stdClass', 'stdClass']);
Assert::same(['implement' => Iface::class, 'references' => ['stdClass', 'stdClass']], $processor->normalizeStructure($statement));

$statement = new Statement(Iface::class, ['tagged' => 123]);
Assert::same(['implement' => Iface::class, 'tagged' => 123], $processor->normalizeStructure($statement));


// aliases
Assert::same(['type' => 'val'], $processor->normalizeStructure(['class' => 'val']));
Assert::same(['external' => 'val'], $processor->normalizeStructure(['dynamic' => 'val']));

Assert::exception(function () use ($processor) {
	$processor->normalizeStructure(['class' => 'val', 'type' => 'val']);
}, Nette\InvalidStateException::class, "Options 'class' and 'type' are aliases, use only 'type'.");
