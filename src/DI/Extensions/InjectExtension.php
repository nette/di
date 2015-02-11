<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\DI\Extensions;

use Nette,
	Nette\DI;


/**
 * Calls inject methods and fills @inject properties.
 *
 * @author     David Grudl
 */
class InjectExtension extends DI\CompilerExtension
{
	const TAG_INJECT = 'inject';


	public function beforeCompile()
	{
		foreach ($this->getContainerBuilder()->getDefinitions() as $def) {
			if ($def->getTag(self::TAG_INJECT) && $def->getClass()) {
				$this->updateDefinition($def);
			}
		}
	}


	private function updateDefinition($def)
	{
		$injects = array();
		$properties = DI\Helpers::getInjectProperties(new \ReflectionClass($def->getClass()), $this->getContainerBuilder());
		foreach ($properties as $property => $type) {
			$injects[] = new DI\Statement('$' . $property, array('@\\' . ltrim($type, '\\')));
		}

		foreach (get_class_methods($def->getClass()) as $method) {
			if (substr($method, 0, 6) === 'inject') {
				$injects[] = new DI\Statement($method);
			}
		}

		$setups = $def->getSetup();
		foreach ($injects as $inject) {
			foreach ($setups as $key => $setup) {
				if ($setup->getEntity() === $inject->getEntity()) {
					$inject = $setup;
					unset($setups[$key]);
				}
			}
			array_unshift($setups, $inject);
		}
		$def->setSetup($setups);
	}

}
