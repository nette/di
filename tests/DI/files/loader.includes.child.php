<?php

declare(strict_types=1);

return [
	'parameters' => [
		'me' => ['loader.includes.child.php'],
		'scalar' => 4,
		'list' => [5, 6],
		'force' => [5, 6],
	],

	'includes' => [
		__DIR__ . '/loader.includes.grandchild.neon',
	],
];
