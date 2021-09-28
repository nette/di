<?php

declare(strict_types=1);

return function (Nette\DI\Compiler $compiler) {
	$builder = $compiler->getContainerBuilder();
	$builder->addDefinition(null)
		->setFactory(Service::class);
};
