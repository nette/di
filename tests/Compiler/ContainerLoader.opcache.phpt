<?php declare(strict_types=1);

/**
 * Test: Nette\DI\ContainerLoader invalidates opcache only for a file rebuilt during the process.
 */

namespace Nette\DI {
	function opcache_invalidate(string $file, bool $force = false): bool
	{
		$GLOBALS['invalidated'][] = basename($file);
		return true;
	}
}

namespace {
	use Nette\DI;
	use Tester\Assert;

	require __DIR__ . '/../bootstrap.php';

	if (!function_exists('opcache_invalidate')) { // the loader calls it only when OPcache is loaded
		function opcache_invalidate(string $file, bool $force = false): bool
		{
			return true;
		}
	}


	$tempDir = getTempDir() . '/opcache';
	$key = ['opcache-key', __FILE__];
	$load = fn() => (new DI\ContainerLoader($tempDir, autoRebuild: true))->load(fn() => Assert::fail('Should not be recreated'), $key);

	// container built by an earlier request
	$class = (new DI\ContainerLoader($tempDir))->getClassName($key);
	$file = "$tempDir/$class.php";
	Nette\Utils\FileSystem::write($file, "<?php\nclass {$class}_first extends Nette\\DI\\Container {}\nreturn '{$class}_first';\n");
	Nette\Utils\FileSystem::write(
		"$file.meta",
		serialize([DI\DependencyChecker::Version, [__FILE__ => filemtime(__FILE__)], [], [], [], '']),
	);


	// first load in the process: the cached script is up to date, nothing is invalidated
	$GLOBALS['invalidated'] = [];
	Assert::same("{$class}_first", $load());
	Assert::same([], $GLOBALS['invalidated']);


	// file rebuilt by another process: the script compiled for the previous content is invalidated
	Nette\Utils\FileSystem::write($file, "<?php\nclass {$class}_second extends Nette\\DI\\Container {}\nreturn '{$class}_second';\n");
	Assert::same("{$class}_second", $load());
	Assert::same(["$class.php"], $GLOBALS['invalidated']);
}
