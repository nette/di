<?php

declare(strict_types=1);

use Nette\DI\Config\Expect;
use Nette\DI\Config\Schema;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class MySchema implements Schema
{
	public function flatten(array $configs, array $path = [])
	{
		return implode($configs);
	}


	public function complete($value, array $path = [])
	{
		return "'" . $value . "'";
	}


	public function getDefault(array $path)
	{
		return 'def';
	}
}


function process(Schema $schema, array $configs)
{
	return $schema->complete($schema->flatten($configs));
}


test(function () {
	$expectation = Expect::arrayOf(new MySchema);

	Assert::same([], process($expectation, []));
	Assert::same([], process($expectation, [[]]));
	Assert::same(["'1'"], process($expectation, [[1]]));
	Assert::same(["'1'", "'2'"], process($expectation, [[1], [2]]));
	Assert::same(['key' => "'12'"], process($expectation, [['key' => 1], ['key' => 2]]));
});
