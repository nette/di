<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\DI\Extensions;

use Nette;


/**
 * DI extension.
 *
 * @author     David Grudl
 */
class DIExtension extends Nette\DI\CompilerExtension
{
	public $defaults = array(
		'debugger' => FALSE,
		'accessors' => FALSE,
	);

	/** @var bool */
	private $debugMode;


	public function __construct($debugMode = FALSE)
	{
		$this->debugMode = $debugMode;
	}


	public function loadConfiguration()
	{
		$config = $this->validateConfig($this->defaults);
		if ($config['accessors']) {
			$this->getContainerBuilder()->parameters['container']['accessors'] = TRUE;
		}
	}


	public function afterCompile(Nette\PhpGenerator\ClassType $class)
	{
		$initialize = $class->getMethod('initialize');
		$container = $this->getContainerBuilder();

		if ($this->debugMode && $this->config['debugger']) {
			$initialize->addBody($container->formatPhp('?;', array(
				new Nette\DI\Statement('@Tracy\Bar::addPanel', array(new Nette\DI\Statement('Nette\Bridges\DITracy\ContainerPanel')))
			)));
		}

		foreach (array_filter($container->findByTag('run')) as $name => $on) {
			$initialize->addBody('$this->getService(?);', array($name));
		}

		if (!empty($this->config['accessors'])) {
			$definitions = $container->getDefinitions();
			ksort($definitions);
			foreach ($definitions as $name => $def) {
				if (Nette\PhpGenerator\Helpers::isIdentifier($name)) {
					$type = $def->getImplement() ?: $def->getClass();
					$class->addDocument("@property $type \$$name");
				}
			}
		}
	}

}
