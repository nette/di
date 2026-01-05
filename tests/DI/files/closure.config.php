<?php

declare(strict_types=1);

return function (Nette\DI\ContainerBuilder $builder): void {
	$builder->addDefinition('closureService')
		->setType(stdClass::class);
};
