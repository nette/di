<?php

declare(strict_types=1);

// The Nette Tester command-line runner can be
// invoked through the command: ../vendor/bin/tester .

if (@!include __DIR__ . '/../vendor/autoload.php') {
	echo 'Install Nette Tester using `composer install`';
	exit(1);
}


// configure environment
Tester\Environment::setup();
date_default_timezone_set('Europe/Prague');


// create temporary directory
(function () {
	define('TEMP_DIR', __DIR__ . '/tmp/' . getmypid());

	// garbage collector
	$GLOBALS['\\lock'] = $lock = fopen(__DIR__ . '/lock', 'w');
	if (rand(0, 100)) {
		flock($lock, LOCK_SH);
		@mkdir(dirname(TEMP_DIR));
	} elseif (flock($lock, LOCK_EX)) {
		Tester\Helpers::purge(dirname(TEMP_DIR));
	}

	@mkdir(TEMP_DIR);
})();


function test(\Closure $function): void
{
	$function();
}


class Notes
{
	public static $notes = [];


	public static function add($message): void
	{
		self::$notes[] = $message;
	}


	public static function fetch(): array
	{
		$res = self::$notes;
		self::$notes = [];
		return $res;
	}
}


function createContainer($source, $config = null, $params = []): ?Nette\DI\Container
{
	$class = 'Container' . md5((string) lcg_value());
	if ($source instanceof Nette\DI\ContainerBuilder) {
		$code = (new Nette\DI\PhpGenerator($source))->generate($class);

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

	file_put_contents(TEMP_DIR . '/code.php', "<?php\n\n$code");
	require TEMP_DIR . '/code.php';
	return new $class($params);
}
