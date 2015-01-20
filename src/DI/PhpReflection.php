<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\DI;

use Nette;


/**
 * PHP reflection helpers.
 *
 * @author     David Grudl
 * @internal
 */
class PhpReflection
{

	/**
	 * Returns an annotation value.
	 * @return string|NULL
	 */
	public static function parseAnnotation(\Reflector $ref, $name)
	{
		if (preg_match("#\\s@$name(?:\\s++([^@]\\S*))?#", $ref->getDocComment(), $m)) {
			return isset($m[1]) ? $m[1] : TRUE;
		}
	}


	/**
	 * Returns declaring class or trait.
	 * @return \ReflectionClass
	 */
	public static function getDeclaringClass(\ReflectionProperty $prop)
	{
		if (PHP_VERSION_ID >= 50400) {
			foreach ($prop->getDeclaringClass()->getTraits() as $trait) {
				if ($trait->hasProperty($prop->getName())) {
					return self::getDeclaringClass($trait->getProperty($prop->getName()));
				}
			}
		}
		return $prop->getDeclaringClass();
	}


	/**
	 * @return string
	 */
	public static function getPropertyType(\ReflectionParameter $prop)
	{
		try {
			return ($ref = $prop->getClass()) ? $ref->getName() : NULL;
		} catch (\ReflectionException $e) {
			if (preg_match('#Class (.+) does not exist#', $e->getMessage(), $m)) {
				return $m[1];
			}
			throw $e;
		}
	}

}
