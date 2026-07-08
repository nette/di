<?php declare(strict_types=1);

/**
 * Test: Tracy ContainerPanel renders a container that has a service whose creator is a
 * first-class callable (getEntity() returns null) without error.
 */

use Nette\Bridges\DITracy\ContainerPanel;
use Nette\DI;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$container = createContainer(new DI\Compiler, '
services:
	fn:
		create: ::trim(...)
		autowired: false
	svc: stdClass
');

$panel = new ContainerPanel($container);

$tab = $panel->getTab();
$body = $panel->getPanel();

Assert::notSame('', $tab);
Assert::contains('fn', $body);
Assert::contains('svc', $body);
