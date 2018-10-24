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
