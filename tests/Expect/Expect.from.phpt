<?php

declare(strict_types=1);

use Nette\DI\Config\Expect;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::with(Expect::class, function () {
	$expectation = Expect::from(new stdClass);

	Assert::same('structure', $expectation->type);
	Assert::same([], $expectation->items);
	Assert::same(stdClass::class, $expectation->castTo);
});


Assert::with(Expect::class, function () {
	$expectation = Expect::from(new class {
		/** @var string */
		public $dsn = 'mysql';
		/** @var string|null */
		public $user;
		/** @var ?string */
		public $password;
		/** @var string[] */
		public $options = [1];
		/** @var bool */
		public $debugger = true;
		public $mixed;
		/** @var array */
		public $arr;
		/** @var string */
		public $required;
	});

	Assert::same('structure', $expectation->type);
	Assert::equal([
		'dsn' => Expect::string('mysql'),
		'user' => Expect::type('string|null'),
		'password' => Expect::type('?string'),
		'options' => Expect::type('string[]')->default([1]),
		'debugger' => Expect::bool(true),
		'mixed' => Expect::mixed(),
		'arr' => Expect::type('array')->default(null)->required(),
		'required' => Expect::type('string')->required(),
	], $expectation->items);
	Assert::type('string', $expectation->castTo);
});


Assert::exception(function () {
	Expect::from(new class {
		/** @var Unknown */
		public $unknown;
	});
}, Nette\NotImplementedException::class, 'Anonymous classes are not supported.');


Assert::with(Expect::class, function () { // overwritten item
	$expectation = Expect::from(new class {
		/** @var string */
		public $dsn = 'mysql';
		/** @var string|null */
		public $user;
	}, ['dsn' => Expect::int(123)]);

	Assert::equal([
		'dsn' => Expect::int(123),
		'user' => Expect::type('string|null'),
	], $expectation->items);
});


Assert::with(Expect::class, function () { // nested object
	$obj = new class {
		/** @var object */
		public $inner;
	};
	$obj->inner = new class {
		/** @var string */
		public $name;
	};

	$expectation = Expect::from($obj);

	Assert::equal([
		'inner' => Expect::structure([
			'name' => Expect::string()->required(),
		])->castTo(get_class($obj->inner)),
	], $expectation->items);
});
