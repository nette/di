<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Extensions;

use Nette;
use Nette\DI\Definitions;
use Nette\Schema\Expect;
use function is_array, sprintf;


/**
 * Decorators for services.
 */
final class DecoratorExtension extends Nette\DI\CompilerExtension
{
	/** @var array<class-string, object{setup: list<mixed>, tags: array<string, mixed>, inject: ?bool}> */
	protected $config = [];


	public function getConfigSchema(): Nette\Schema\Schema
	{
		return Expect::arrayOf(
			Expect::structure([
				'setup' => Expect::list(),
				'tags' => Expect::array(),
				'inject' => Expect::bool(),
			]),
		);
	}


	public function beforeCompile(): void
	{
		$this->getContainerBuilder()->resolve();
		foreach ($this->config as $type => $info) {
			if (!class_exists($type) && !interface_exists($type)) {
				throw new Nette\DI\InvalidConfigurationException(sprintf("Decorated class '%s' not found.", $type));
			}

			if ($info->inject !== null) {
				$info->tags[InjectExtension::TagInject] = $info->inject;
			}

			$this->addSetups($type, Nette\DI\Helpers::filterArguments($info->setup));
			$this->addTags($type, $info->tags);
		}
	}


	/**
	 * @param  class-string  $type
	 * @param  array<Definitions\Statement|array<mixed>>  $setups
	 */
	public function addSetups(string $type, array $setups): void
	{
		foreach ($this->findByType($type) as $def) {
			if ($def instanceof Definitions\FactoryDefinition) {
				$def = $def->getResultDefinition();
			}

			if (!$def instanceof Definitions\ServiceDefinition) {
				continue;
			}

			foreach ($setups as $setup) {
				if (is_array($setup)) {
					$setup = new Definitions\Statement((string) key($setup), array_values($setup));
				}

				$def->addSetup($setup);
			}
		}
	}


	/**
	 * @param  class-string  $type
	 * @param  array<string, mixed>  $tags
	 */
	public function addTags(string $type, array $tags): void
	{
		$tags = Nette\Utils\Arrays::normalize($tags, filling: true);
		foreach ($this->findByType($type) as $def) {
			$def->setTags($def->getTags() + $tags);
		}
	}


	/**
	 * @param  class-string  $type
	 * @return array<string, Definitions\Definition>
	 */
	private function findByType(string $type): array
	{
		return array_filter(
			$this->getContainerBuilder()->getDefinitions(),
			fn(Definitions\Definition $def): bool => ($def->getType() !== null && is_a($def->getType(), $type, allow_string: true))
				|| ($def instanceof Definitions\FactoryDefinition && $def->getResultType() !== null && is_a($def->getResultType(), $type, allow_string: true)),
		);
	}
}
