<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Extensions;

use Nette;
use Nette\DI\Config\Expect;
use Nette\DI\Definitions;

/**
 * Decorators for services.
 */
final class DecoratorExtension extends Nette\DI\CompilerExtension
{
	public function getConfigSchema(): Nette\DI\Config\Schema
	{
		return Expect::arrayOf(
			Expect::structure([
				'setup' => Expect::list(),
				'tags' => Expect::array(),
				'inject' => Expect::bool(),
			])
		);
	}


	public function beforeCompile()
	{
		foreach ($this->config as $type => $info) {
			if ($info->inject !== null) {
				$info->tags[InjectExtension::TAG_INJECT] = $info->inject;
			}
			$this->addSetups($type, Nette\DI\Config\Processor::processArguments($info->setup));
			$this->addTags($type, Nette\DI\Config\Processor::processArguments($info->tags));
		}
	}


	public function addSetups(string $type, array $setups): void
	{
		foreach ($this->findByType($type) as $def) {
			if ($def instanceof Definitions\FactoryDefinition) {
				$def = $def->getResultDefinition();
			}
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
		return array_filter($this->getContainerBuilder()->getDefinitions(), function (Definitions\Definition $def) use ($type): bool {
			return is_a($def->getType(), $type, true)
				|| ($def instanceof Definitions\FactoryDefinition && is_a($def->getResultType(), $type, true));
		});
	}
}
