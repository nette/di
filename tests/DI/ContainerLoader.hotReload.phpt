<?php declare(strict_types=1);

/**
 * Test: Nette\DI\ContainerLoader live reload of regenerated containers
 * in long-running processes (auto-rebuild mode).
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$tempDir = getTempDir() . '/hotreload';
$loader = new DI\ContainerLoader($tempDir, autoRebuild: true);

// Generator builds a tiny class body whose phpdoc tracks generation count
// so we can detect "did this version of the code actually run?".
$generation = 0;
$generator = function (DI\Compiler $compiler) use (&$generation): string {
	$generation++;
	$class = $compiler->getContainerBuilder()->parameters['__class'];
	return "/* generation: $generation */\nclass $class extends \\Nette\\DI\\Container {}";
};

$key = ['hot-reload-key', __FILE__];
$class = $loader->getClassName($key);


// 1) First load: container generated, class loaded under canonical name.
$before = $generation;
$returned = $loader->load(function (DI\Compiler $c) use ($generator, $class) {
	$c->getContainerBuilder()->parameters['__class'] = $class;
	return $generator($c);
}, $key);
Assert::same($class, $returned, 'First load returns canonical class name');
Assert::true(class_exists($class, autoload: false), 'Canonical class is loaded');
Assert::same($before + 1, $generation, 'Generator was invoked exactly once');


// 2) Second load with unchanged config: cache hit, no regeneration, same class.
$before = $generation;
$returned = $loader->load(function (DI\Compiler $c) use ($generator, $class) {
	$c->getContainerBuilder()->parameters['__class'] = $class;
	return $generator($c);
}, $key);
Assert::same($class, $returned, 'Cache hit returns canonical class name');
Assert::same($before, $generation, 'Generator was NOT invoked (cache hit)');


// 3) Simulate config change: invalidate the cached container's dependency hash
//    so isExpired() returns true, then load again.
$file = (new ReflectionClass($class))->getFileName();
$meta = unserialize(file_get_contents("$file.meta"));
$meta[1][__FILE__] = 0; // tracked file mtime mutated → DependencyChecker returns expired
file_put_contents("$file.meta", serialize($meta));

$before = $generation;
$returned = $loader->load(function (DI\Compiler $c) use ($generator, $class) {
	$c->getContainerBuilder()->parameters['__class'] = $class;
	return $generator($c);
}, $key);

// 3a) Returned name must be the unique reload variant, NOT the canonical name.
Assert::notSame($class, $returned, 'After config change the returned class differs from canonical');
Assert::match($class . '_R%h%', $returned, 'Reload class follows naming convention');
Assert::true(class_exists($returned, autoload: false), 'Reload class is loaded');
Assert::true(is_subclass_of($returned, DI\Container::class), 'Reload class extends DI\Container');
Assert::same($before + 1, $generation, 'Generator was invoked once for regeneration');


// 4) Both classes coexist independently — original is still around.
Assert::true(class_exists($class, autoload: false), 'Canonical class is still loaded');
Assert::notSame($class, $returned, 'Reload class is distinct symbol');


// 5) Multiple reloads produce distinct class names.
$file = (new ReflectionClass($class))->getFileName();
$meta = unserialize(file_get_contents("$file.meta"));
$meta[1][__FILE__] = 1;
file_put_contents("$file.meta", serialize($meta));

$returned2 = $loader->load(function (DI\Compiler $c) use ($generator, $class) {
	$c->getContainerBuilder()->parameters['__class'] = $class;
	return $generator($c);
}, $key);

Assert::notSame($returned, $returned2, 'Each reload produces a distinct unique class');
Assert::match($class . '_R%h%', $returned2, 'Second reload also follows naming convention');


// 6) Without autoRebuild, the loader never reaches the reload branch even
//    if its cached container file is regenerated externally.
$prodLoader = new DI\ContainerLoader($tempDir . '/prod', autoRebuild: false);
$prodKey = ['prod-key'];
$prodClass = $prodLoader->getClassName($prodKey);

$prodLoader->load(fn(DI\Compiler $c): string => "class $prodClass extends \\Nette\\DI\\Container {}", $prodKey);
Assert::true(class_exists($prodClass, autoload: false));

// Even after the meta is invalidated, autoRebuild=false means isExpired() returns false → cache hit.
$prodFile = (new ReflectionClass($prodClass))->getFileName();
$prodMeta = unserialize(file_get_contents("$prodFile.meta"));
$prodMeta[1][__FILE__] = 0;
file_put_contents("$prodFile.meta", serialize($prodMeta));

$prodReturned = $prodLoader->load(function (DI\Compiler $c) use ($prodClass): string {
	Assert::fail('Generator must NOT be invoked when autoRebuild=false');
	return "class $prodClass extends \\Nette\\DI\\Container {}";
}, $prodKey);
Assert::same($prodClass, $prodReturned, 'autoRebuild=false always returns canonical class name');
