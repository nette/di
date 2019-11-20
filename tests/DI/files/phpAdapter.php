<?php

declare(strict_types=1);

return [
	'webname' => 'the example',
	'database' => [
		'adapter' => 'pdo_mysql',
		'params' => [
			'host' => 'db.example.com',
			'username' => 'dbuser',
			'password' => '*secret*',
			'dbname' => 'dbname',
		],
	],
];
