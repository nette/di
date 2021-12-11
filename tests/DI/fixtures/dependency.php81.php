<?php

declare(strict_types=1);


class NotSerializable
{
	function __sleep()
	{
		throw new Exception;
	}
}


class Dep1
{
	public function f($a = new NotSerializable)
	{
	}
}
