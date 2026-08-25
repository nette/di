<?php declare(strict_types=1);

/**
 * Test: Nette\DI\ContainerLoader live reload of regenerated containers
 * in long-running processes (auto-rebuild mode).
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$tempDir = getTempDir() . '/hotreload';
$key = ['hot-reload-key', __FILE__];
$generation = 0;

// each call creates a fresh loader, like Bootstrap\Configurator does
$load = function (bool $autoRebuild = true) use (&$generation, $key, $tempDir): string {
	$loader = new DI\ContainerLoader($tempDir, $autoRebuild);
	return $loader->load(function (DI\Compiler $compiler) use (&$generation): void {
		$generation++;
		$compiler->getContainerBuilder()->addDefinition("gen$generation")->setType(stdClass::class);
	}, $key);
};

$expire = function (string $file, int $mtime): void {
	$meta = unserialize(file_get_contents("$file.meta"));
	$meta[1][__FILE__] = $mtime; // tracked file mtime mutated → DependencyChecker reports expired
	file_put_contents("$file.meta", serialize($meta));
};

$class = (new DI\ContainerLoader($tempDir))->getClassName($key);
$file = "$tempDir/$class.php";


// first load: container generated under a unique name, getClassName() is its alias
$first = $load();
Assert::same(1, $generation);
Assert::match($class . '_%h%', $first);
Assert::true((new $first)->hasService('gen1'));
Assert::true(class_exists($class, autoload: false));
Assert::same($first, (new ReflectionClass($class))->getName());
Assert::same(realpath($file), realpath((new ReflectionClass($first))->getFileName()));


// unchanged config: cache hit, no regeneration
Assert::same($first, $load());
Assert::same(1, $generation);


// config change: regenerated code is loaded as another class, the original one stays
$expire($file, 0);
$second = $load();
$secondCode = file_get_contents($file);
Assert::same(2, $generation);
Assert::notSame($first, $second);
Assert::match($class . '_%h%', $second);
Assert::true((new $second)->hasService('gen2'));
Assert::true(class_exists($first, autoload: false));
Assert::same($first, (new ReflectionClass($class))->getName()); // alias keeps pointing to the first one


// unchanged config after reload: the reloaded class is returned again, not the first one
Assert::same($second, $load());
Assert::same(2, $generation);


// file rebuilt by another process (no expiration visible): the new content is detected and loaded
$expire($file, 1);
$third = (function () use ($tempDir, $key, $class): string {
	$unique = $class . '_external';
	file_put_contents("$tempDir/$class.php", "<?php\nclass $unique extends Nette\\DI\\Container {}\nreturn '$unique';\n");
	$meta = unserialize(file_get_contents("$tempDir/$class.php.meta"));
	$meta[1][__FILE__] = filemtime(__FILE__);
	file_put_contents("$tempDir/$class.php.meta", serialize($meta));
	return $unique;
})();
Assert::same($third, $load());
Assert::same(2, $generation);


// file reverted to a content that is already loaded: that class is reused, nothing is included again
$thirdCode = file_get_contents($file);
file_put_contents($file, $secondCode);
Assert::same($second, $load()); // would be "Cannot redeclare class" if included
Assert::same(2, $generation);
file_put_contents($file, $thirdCode);


// file compiled by an older version (declares the base name itself, returns nothing)
$legacyDir = "$tempDir/legacy";
$legacyClass = (new DI\ContainerLoader($legacyDir))->getClassName('legacy');
Nette\Utils\FileSystem::write("$legacyDir/$legacyClass.php", "<?php\nclass $legacyClass extends Nette\\DI\\Container {}\n");
Nette\Utils\FileSystem::write("$legacyDir/$legacyClass.php.meta", file_get_contents("$file.meta"));
$loader = new DI\ContainerLoader($legacyDir, autoRebuild: true);
Assert::same($legacyClass, $loader->load(fn() => Assert::fail('Should not be recreated'), 'legacy'));
Assert::same($legacyClass, $loader->load(fn() => Assert::fail('Should not be recreated'), 'legacy'));


// without autoRebuild the base class name is always returned, even if the cache is expired
$expire($file, 0);
Assert::same($class, $load(autoRebuild: false));
Assert::same(2, $generation);
