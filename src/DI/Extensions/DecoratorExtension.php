<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Extensions;

use Nette;
use Nette\DI\Definitions;

/**
 * Decorators for services.
 */
final class DecoratorExtension extends Nette\DI\CompilerExtension
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


	public function addSetups(string $type, array $setups): void
	{
		foreach ($this->findByType($type) as $def) {
			foreach ($setups as $setup) {
				if (is_array($setup)) {
					$setup = new Definitions\Statement(key($setup), array_values($setup));
				}
				$def->addSetup($setup);
			}
		}
	}


	public function addTags(string $type, array $tags): void
	{
		$tags = Nette\Utils\Arrays::normalize($tags, true);
		foreach ($this->findByType($type) as $def) {
			$def->setTags($def->getTags() + $tags);
		}
	}


	private function findByType(string $type): array
	{
		return array_filter($this->getContainerBuilder()->getDefinitions(), function (Nette\DI\ServiceDefinition $def) use ($type): bool {
			return is_a($def->getImplement(), $type, true)
				|| ($def->getImplementMode() !== $def::IMPLEMENT_MODE_GET && is_a($def->getType(), $type, true));
		});
	}
}
