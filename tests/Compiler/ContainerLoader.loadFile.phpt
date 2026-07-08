<?php declare(strict_types=1);

/**
 * Test: Nette\DI\ContainerLoader: file caching and reuse.
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$loader = new DI\ContainerLoader(getTempDir() . '/subdir', autoRebuild: true);

// create container
Assert::with($loader, function () {
	$this->loadFile('class1', function () {});
});

// ensure files are created
$file = (new ReflectionClass('class1'))->getFileName();
Assert::true(is_file($file));
Assert::true(is_file("$file.meta"));

// load again
file_put_contents($file, ''); // remove file to avoid class redeclare error
Assert::with($loader, function () {
	$this->loadFile('class1', function () { Assert::fail('Should not be recreated'); });
});
