<?php
declare(strict_types=1);

class CountableClass extends ClassOk2 implements Countable
{
	public function count(): int
	{
	}
}
