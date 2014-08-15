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

	public function beforeCompile()
	{
		foreach ($this->getContainerBuilder()->getDefinitions() as $def) {
			if ($def->inject && $def->class) {
				$this->updateDefinition($def);
			}

		}
	}


	private function updateDefinition($def)
	{
		$injects = array();
		$properties = DI\Helpers::getInjectProperties(Nette\Reflection\ClassType::from($def->class), $this->getContainerBuilder());
		foreach ($properties as $property => $type) {
			$injects[] = new DI\Statement('$' . $property, array('@\\' . ltrim($type, '\\')));
		}

		foreach (get_class_methods($def->class) as $method) {
			if (substr($method, 0, 6) === 'inject') {
				$injects[] = new DI\Statement($method);
			}
		}

		$setups = $def->setup;
		foreach ($injects as $inject) {
			foreach ($setups as $key => $setup) {
				if ($setup->entity === $inject->entity) {
					$inject = $setup;
					unset($setups[$key]);
				}
			}
			array_unshift($setups, $inject);
		}
		$def->setup = $setups;
	}

}
