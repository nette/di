<?php

declare(strict_types=1);

use Nette\DI\Helpers;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


function testIt(string $type, $val, $res = null)
{
	if (func_num_args() === 3) {
		Assert::same($res, Helpers::convertType($val, $type));
	} else {
		Assert::exception(
			fn() => Helpers::convertType($val, $type),
			Nette\InvalidStateException::class,
		);
	}
}


$obj = new stdClass;

testIt('string', null);
testIt('string', []);
testIt('string', $obj);
testIt('string', '', '');
testIt('string', 'a', 'a');
testIt('string', '0', '0');
testIt('string', '1', '1');
testIt('string', '1.0', '1.0');
testIt('string', '1.1', '1.1');
testIt('string', '1a', '1a');
testIt('string', true, '1');
testIt('string', false, '0');
testIt('string', 0, '0');
testIt('string', 1, '1');
testIt('string', 1.0, '1');
testIt('string', 1.2, '1.2');

testIt('int', null);
testIt('int', []);
testIt('int', $obj);
testIt('int', '');
testIt('int', 'a');
testIt('int', '0', 0);
testIt('int', '1', 1);
testIt('int', '1.0');
testIt('int', '1.1');
testIt('int', '1a');
testIt('int', true, 1);
testIt('int', false, 0);
testIt('int', 0, 0);
testIt('int', 1, 1);
testIt('int', 1.0, 1);
testIt('int', 1.2);

testIt('float', null);
testIt('float', []);
testIt('float', $obj);
testIt('float', '');
testIt('float', 'a');
testIt('float', '0', 0.0);
testIt('float', '1', 1.0);
testIt('float', '1.', 1.0);
testIt('float', '1.0', 1.0);
testIt('float', '1.00', 1.0);
testIt('float', '1..0');
testIt('float', '1.1', 1.1);
testIt('float', '1a');
testIt('float', true, 1.0);
testIt('float', false, 0.0);
testIt('float', 0, 0.0);
testIt('float', 1, 1.0);
testIt('float', 1.0, 1.0);
testIt('float', 1.2, 1.2);

testIt('bool', null);
testIt('bool', []);
testIt('bool', $obj);
testIt('bool', '');
testIt('bool', 'a');
testIt('bool', '1', true);
testIt('bool', '1.0');
testIt('bool', '1.1');
testIt('bool', '1a');
testIt('bool', true, true);
testIt('bool', false, false);
testIt('bool', 0, false);
testIt('bool', 1, true);
testIt('bool', 1.0, true);
testIt('bool', 1.2);
