<?php

/**
 * Test: Nette\DI\Config\Processor::normalizeDefinition()
 */

declare(strict_types=1);

use Nette\DI\Config\Processor;
use Nette\DI\Definitions\Statement;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface IFace
{
}

$processor = new Processor;
Assert::same([], $processor->normalizeDefinition(null));
Assert::same([], $processor->normalizeDefinition([]));
Assert::same([false], $processor->normalizeDefinition(false));
Assert::same(['factory' => true], $processor->normalizeDefinition(true));
Assert::same(['factory' => 'class'], $processor->normalizeDefinition('class'));
Assert::same(['implement' => Iface::class], $processor->normalizeDefinition(Iface::class));
Assert::same(['factory' => ['class', 'method']], $processor->normalizeDefinition(['class', 'method']));
Assert::same(['factory' => [Iface::class, 'method']], $processor->normalizeDefinition([Iface::class, 'method']));

$statement = new Statement(['class', 'method']);
Assert::same(['factory' => $statement], $processor->normalizeDefinition($statement));

$statement = new Statement(Iface::class, ['foo']);
Assert::same(['implement' => Iface::class, 'factory' => 'foo'], $processor->normalizeDefinition($statement));
