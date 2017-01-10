<?php

/**
 * Test: Nette\DI\Config\Adapters\IniAdapter
 */

declare(strict_types=1);

use Nette\DI\Config;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

define('TEMP_FILE', TEMP_DIR . '/cfg.ini');


$config = new Config\Loader;
$data = @$config->load('files/iniAdapter.ini', 'production'); // @ deprecated
Assert::same([
	'webname' => 'the example',
	'database' => [
		'adapter' => 'pdo_mysql',
		'params' => [
			'host' => 'db.example.com',
			'username' => 'dbuser',
			'password' => 'secret',
			'dbname' => 'dbname',
		],
	],
], $data);


$data = @$config->load('files/iniAdapter.ini', 'development'); // @ deprecated
Assert::same([
	'webname' => 'the example',
	'database' => [
		'adapter' => 'pdo_mysql',
		'params' => [
			'host' => 'dev.example.com',
			'username' => 'devuser',
			'password' => 'devsecret',
			'dbname' => 'dbname',
		],
	],
	'timeout' => '10',
	'display_errors' => '1',
	'html_errors' => '',
	'items' => ['10', '20'],
	'php' => [
		'zlib.output_compression' => '1',
		'date.timezone' => 'Europe/Prague',
	],
], $data);


$config->save($data, TEMP_FILE);
Assert::match(<<<EOD
; generated by Nette

webname = "the example"
database.adapter = "pdo_mysql"
database.params.host = "dev.example.com"
database.params.username = "devuser"
database.params.password = "devsecret"
database.params.dbname = "dbname"
timeout = 10
display_errors = 1
html_errors = ""
items.0 = 10
items.1 = 20
php.zlib..output_compression = 1
php.date..timezone = "Europe/Prague"
EOD
, file_get_contents(TEMP_FILE));


$data = @$config->load('files/iniAdapter.ini'); // @ deprecated
$config->save($data, TEMP_FILE);
Assert::match(<<<EOD
; generated by Nette

[production]
webname = "the example"
database.adapter = "pdo_mysql"
database.params.host = "db.example.com"
database.params.username = "dbuser"
database.params.password = "secret"
database.params.dbname = "dbname"

[development < production]
database.params.host = "dev.example.com"
database.params.username = "devuser"
database.params.password = "devsecret"
timeout = 10
display_errors = 1
html_errors = ""
items.0 = 10
items.1 = 20
php.zlib..output_compression = 1
php.date..timezone = "Europe/Prague"
EOD
, file_get_contents(TEMP_FILE));
