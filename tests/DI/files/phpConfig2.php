<?php

declare(strict_types=1);

return function (Nette\DI\Compiler $compiler) {
	$compiler->addConfig([
		'parameters' => [
			'foo' => 123,
		],
	]);
};
