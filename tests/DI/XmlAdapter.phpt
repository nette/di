<?php

/**
 * Test: Nette\DI\Config\Adapters\xmldapter
 */

use Nette\DI\Config;
use Nette\DI\Statement;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

define('TEMP_FILE', TEMP_DIR . '/cfg.xml');


$config = new Config\Loader;
$data = $config->load('files/xmlAdapter.xml', 'production');
Assert::same([
	'webname' => 'the example',
	'database' => [
		'adapter' => 'pdo_mysql',
		'params' => [
			'host' => 'db.example.com',
			'username' => 'dbuser',
			'password' => 'secret ',
			'dbname' => 'dbname',
		],
	],
], $data);


$data = $config->load('files/xmlAdapter.xml', 'development');
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
	'timeout' => 10,
	'display_errors' => TRUE,
	'html_errors' => FALSE,
	'items' => [10, 20],
	'php' => [
		'zlib.output_compression' => TRUE,
		'date.timezone' => 'Europe/Prague',
	],
], $data);


$config->save($data, TEMP_FILE);
Assert::match(<<<EOD
<?xml version="1.0"?>
<config xmlns:nc="http://www.nette.org/xmlns/nette/config/1.0" xmlns="http://www.nette.org/xmlns/nette/config/1.0"><webname>the example</webname><database><adapter>pdo_mysql</adapter><params><host>dev.example.com</host><username>devuser</username><password>devsecret</password><dbname>dbname</dbname></params></database><timeout number="10"/><display_errors bool="1"/><html_errors bool="0"/><items array="numeric"><item number="10"/><item number="20"/></items><php><zlib.output_compression bool="1"/><date.timezone>Europe/Prague</date.timezone></php></config>
EOD
, file_get_contents(TEMP_FILE));


$data = $config->load('files/xmlAdapter.xml');
$config->save($data, TEMP_FILE);
Assert::match(<<<EOD
<?xml version="1.0"?>
<config xmlns:nc="http://www.nette.org/xmlns/nette/config/1.0" xmlns="http://www.nette.org/xmlns/nette/config/1.0"><production><webname>the example</webname><database><adapter>pdo_mysql</adapter><params><host>db.example.com</host><username>dbuser</username><password>secret </password><dbname>dbname</dbname></params></database></production><development extends="production"><database><params><host>dev.example.com</host><username>devuser</username><password>devsecret</password></params></database><timeout number="10"/><display_errors bool="1"/><html_errors bool="0"/><items array="numeric"><item number="10"/><item number="20"/></items><php><zlib.output_compression bool="1"/><date.timezone>Europe/Prague</date.timezone></php></development><nothing/></config>
EOD
, file_get_contents(TEMP_FILE));


$data = $config->load('files/xmlAdapter.entity.xml');
Assert::equal([
	new Statement('ent', [1]),
	new Statement([
			new Statement('ent', [2]),
			'inner',
		],
		['3', '4']
	),
	new Statement([
			new Statement('ent', ['3']),
			'inner',
		],
		['5','6']
	),
], $data);

$data = $config->load('files/xmlAdapter.entity.xml');
$config->save($data, TEMP_FILE);
Assert::match(<<<EOD
<?xml version="1.0"?>
<config xmlns:nc="http://www.nette.org/xmlns/nette/config/1.0" xmlns="http://www.nette.org/xmlns/nette/config/1.0" array="numeric"><item statement="statement"><s><ent>ent</ent><args array="numeric"><item number="1"/></args></s></item><item statement="statement"><s><ent>ent</ent><args array="numeric"><item number="2"/></args></s><s><ent>inner</ent><args array="numeric"><item>3</item><item>4</item></args></s></item><item statement="statement"><s><ent>ent</ent><args array="numeric"><item>3</item></args></s><s><ent>inner</ent><args array="numeric"><item>5</item><item>6</item></args></s></item></config>
EOD
, file_get_contents(TEMP_FILE));
