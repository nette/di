<?php

/**
 * @phpVersion 8.0
 */

declare(strict_types=1);

use Nette\DI;
use Nette\DI\Definitions\Statement;
use Tester\Assert;


class Factory
{
	public function createUnion(): stdClass|array
	{
		return [];
	}
}


require __DIR__ . '/../bootstrap.php';


Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setCreator([new Statement([Factory::class, 'createUnion']), 'next']);
	$container = createContainer($builder);
}, Nette\DI\ServiceCreationException::class, "Service 'a': Return type of Factory::createUnion() is expected to not be built-in/complex, 'stdClass|array' given.");
