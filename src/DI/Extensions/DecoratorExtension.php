<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\DI\Extensions;

use Nette,
	Nette\DI\Statement;


/**
 * Decorators for services.
 *
 * @author     David Grudl
 */
class DecoratorExtension extends Nette\DI\CompilerExtension
{
	public $defaults = array(
		'setup' => array(),
		'tags' => array(),
		'inject' => NULL,
	);


	public function beforeCompile()
	{
		foreach ($this->getConfig() as $class => $info) {
			$info = $this->validateConfig($this->defaults, $info, $this->prefix($class));
			if ($info['inject'] !== NULL) {
				$info['tags'][InjectExtension::TAG_INJECT] = $info['inject'];
			}
			$this->addSetups($class, (array) $info['setup']);
			$this->addTags($class, (array) $info['tags']);
		}
	}


	public function addSetups($type, array $setups)
	{
		foreach ($this->findByType($type) as $def) {
			foreach ($setups as $setup) {
				$def->addSetup($setup);
			}
		}
	}


	public function addTags($type, array $tags)
	{
		$tags = Nette\Utils\Arrays::normalize($tags, TRUE);
		foreach ($this->findByType($type) as $def) {
			$def->setTags($def->getTags() + $tags);
		}
	}


	private function findByType($type)
	{
		$type = ltrim($type, '\\');
		return array_filter($this->getContainerBuilder()->getDefinitions(), function($def) use ($type) {
			return $def->getClass() === $type
				|| is_subclass_of($def->getClass(), $type)
				|| (PHP_VERSION_ID < 50307 && array_key_exists($type, class_implements($def->getClass())));
		});
	}

}
