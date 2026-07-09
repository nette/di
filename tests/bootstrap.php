<?php declare(strict_types=1);

// The Nette Tester command-line runner can be
// invoked through the command: ../vendor/bin/tester .

if (@!include __DIR__ . '/../vendor/autoload.php') {
	echo 'Install Nette Tester using `composer install`';
	exit(1);
}


// configure environment
Tester\Environment::setup();
Tester\Environment::setupFunctions();


function getTempDir(): string
{
	$dir = __DIR__ . '/tmp/' . getmypid();

	if (empty($GLOBALS['\lock'])) {
		// garbage collector
		$GLOBALS['\lock'] = $lock = fopen(__DIR__ . '/lock', 'w');
		if (rand(0, 100)) {
			flock($lock, LOCK_SH);
			@mkdir(dirname($dir));
		} elseif (flock($lock, LOCK_EX)) {
			Tester\Helpers::purge(dirname($dir));
		}

		@mkdir($dir);
	}

	return $dir;
}


function createContainer($source, $config = null, array $params = []): ?Nette\DI\Container
{
	$class = 'Container' . @++$GLOBALS['counter'];
	if ($source instanceof Nette\DI\ContainerBuilder) {
		$source->complete();
		$code = (new Nette\DI\Compiler\PhpGenerator($source))->generate($class);

	} elseif ($source instanceof Nette\DI\Compiler) {
		if (is_string($config)) {
			$loader = new Nette\DI\Config\Loader;
			$config = $loader->load(is_file($config) ? $config : Tester\FileMock::create($config, 'neon'));
		}
		$code = $source->addConfig((array) $config)
			->setClassName($class)
			->compile();
	} else {
		return null;
	}

	file_put_contents(getTempDir() . '/code.php', "<?php\n\n$code");
	require getTempDir() . '/code.php';
	return new $class($params);
}
