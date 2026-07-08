<?php declare(strict_types=1);

namespace Nette\DI; // bare charm: the Definitions type and the element functions are all here

// Phase is not in this namespace, so it needs importing

return function (Definitions $di): void {
	// immediate: my own service, added before extensions
	$di->add('fromClosure', create(\ClosureService::class, ['hello']));

	// deferred: a Modify hook is the way to touch the world after registration
	$di->hook(Phase::Modify, function (Definitions $di): void {
		$di->get('fromClosure')->setup('$touched', [true]);
	});
};
