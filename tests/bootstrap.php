<?php

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
define('TEMP_DIR', __DIR__ . '/tmp/' . lcg_value());
@mkdir(TEMP_DIR, 0777, TRUE); // @ - base directory may already exist
register_shutdown_function(function () {
	Tester\Helpers::purge(TEMP_DIR);
	rmdir(TEMP_DIR);
});


function test(\Closure $function)
{
	$function();
}


class Notes
{
	static public $notes = array();

	public static function add($message)
	{
		self::$notes[] = $message;
	}

	public static function fetch()
	{
		$res = self::$notes;
		self::$notes = array();
		return $res;
	}

}


function createContainer($source, $config = NULL)
{
	$class = 'Container' . md5(lcg_value());
	if ($source instanceof Nette\DI\ContainerBuilder) {
		$code = implode('', $source->generateClasses($class));

	} elseif ($source instanceof Nette\DI\Compiler) {
		if (is_string($config)) {
			$loader = new Nette\DI\Config\Loader;
			$config = $loader->load(is_file($config) ? $config : Tester\FileMock::create($config, 'neon'));
		}
		$code = $source->compile((array) $config, $class, 'Nette\DI\Container');
	} else {
		return;
	}

	file_put_contents(TEMP_DIR . '/code.php', "<?php\n\n$code");
	require TEMP_DIR . '/code.php';
	return new $class;
}
