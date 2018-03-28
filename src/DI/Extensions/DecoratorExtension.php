<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Extensions;

use Nette;


/**
 * Decorators for services.
 */
class DecoratorExtension extends Nette\DI\CompilerExtension
{
	public $defaults = [
		'setup' => [],
		'tags' => [],
		'inject' => null,
	];


	public function beforeCompile()
	{
		foreach ($this->getConfig() as $type => $info) {
			$info = $this->validateConfig($this->defaults, $info, $this->prefix($type));
			if ($info['inject'] !== null) {
				$info['tags'][InjectExtension::TAG_INJECT] = $info['inject'];
			}
			$info = Nette\DI\Helpers::filterArguments($info);
			$this->addSetups($type, (array) $info['setup']);
			$this->addTags($type, (array) $info['tags']);
		}
	}


	public function addSetups($type, array $setups)
	{
		foreach ($this->findByType($type) as $def) {
			foreach ($setups as $setup) {
				if (is_array($setup)) {
					$setup = new Nette\DI\Statement(key($setup), array_values($setup));
				}
				$def->addSetup($setup);
			}
		}
	}


	public function addTags($type, array $tags)
	{
		$tags = Nette\Utils\Arrays::normalize($tags, true);
		foreach ($this->findByType($type) as $def) {
			$def->setTags($def->getTags() + $tags);
		}
	}


	private function findByType($type)
	{
		return array_filter($this->getContainerBuilder()->getDefinitions(), function ($def) use ($type) {
			return is_a($def->getImplement(), $type, true)
				|| ($def->getImplementMode() !== $def::IMPLEMENT_MODE_GET && is_a($def->getType(), $type, true));
		});
	}
}
