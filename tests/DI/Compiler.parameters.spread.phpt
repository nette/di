<?php

/**
 * Test: Nette\DI\Compiler: spread operator
 */

declare(strict_types = 1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class DbConnection
{

	/** @var array */
	public $config;


	public function __construct(array $config)
	{
		$this->config = $config;
	}
}


$container = createContainer(new DI\Compiler, '
parameters:
	connection:
		username: root
		password: 123
	connection2:
		- ...%connection%
		database: app
services:
	connection: DbConnection([timezone: utc, ...%connection2%])
	');


Assert::same([
	'username' => 'root',
	'password' => 123,
	'database' => 'app',
], $container->getParameters()['connection2']);

/** @var DbConnection $connection */
$connection = $container->getService('connection');
Assert::same([
	'timezone' => 'utc',
	'username' => 'root',
	'password' => 123,
	'database' => 'app',
], $connection->config);
