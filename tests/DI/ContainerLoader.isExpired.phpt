<?php

/**
 * Test: Nette\DI\ContainerLoader expiration test.
 */

declare(strict_types=1);

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


// load again, nothing was modified
Assert::with($loader, function () use ($file) {
	Assert::false($this->isExpired($file, $newMeta));
	Assert::null($newMeta);
});


// alter filemtime in files
$meta = file_get_contents("$file.meta");
$altered = unserialize($meta);
$altered[1][__FILE__] = 123;
file_put_contents("$file.meta", serialize($altered));

Assert::with($loader, function () use ($file) {
	Assert::true($this->isExpired($file, $newMeta));
	Assert::null($newMeta);
});


// alter filemtime in classes
$altered = unserialize($meta);
$altered[2][key($altered[2])] = 123;
file_put_contents("$file.meta", serialize($altered));

Assert::with($loader, function () use ($file, $meta) {
	Assert::true($this->isExpired($file, $newMeta));
	Assert::same($meta, $newMeta);
});
