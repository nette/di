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
	$this->loadCurrent('class1', "$this->tempDirectory/class1.php", function () {});
});

// ensure files are created
$file = (new ReflectionClass('class1'))->getFileName();
Assert::true(is_file($file));
Assert::true(is_file("$file.meta"));

// load again
Assert::with($loader, function () {
	$class = $this->loadCurrent('class1', "$this->tempDirectory/class1.php", function () { Assert::fail('Should not be recreated'); });
	Assert::same((new ReflectionClass('class1'))->getName(), $class);
});
