<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Config\Adapters;

use Nette;
use Nette\DI\Config\Helpers;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\Statement;
use Nette\Neon;


/**
 * Reading and generating NEON files.
 */
final class NeonAdapter implements Nette\DI\Config\Adapter
{
	private const PreventMergingSuffix = '!';
	private string $file;


	/**
	 * Reads configuration from NEON file.
	 */
	public function load(string $file): array
	{
		$input = Nette\Utils\FileSystem::read($file);
		if (substr($input, 0, 3) === "\u{FEFF}") { // BOM
			$input = substr($input, 3);
		}

		$this->file = $file;
		$decoder = new Neon\Decoder;
		$node = $decoder->parseToNode($input);
		$traverser = new Neon\Traverser;
		$node = $traverser->traverse($node, $this->firstClassCallableVisitor(...));
		$node = $traverser->traverse($node, $this->removeUnderscoreVisitor(...));
		$node = $traverser->traverse($node, $this->convertAtSignVisitor(...));
		$node = $traverser->traverse($node, $this->deprecatedParametersVisitor(...));
		$node = $traverser->traverse($node, $this->resolveConstants(...));
		return $this->process((array) $node->toValue());
	}


	/** @throws Nette\InvalidStateException */
	public function process(array $arr): array
	{
		$res = [];
		foreach ($arr as $key => $val) {
			if (is_string($key) && str_ends_with($key, self::PreventMergingSuffix)) {
				if (!is_array($val) && $val !== null) {
					throw new Nette\DI\InvalidConfigurationException(sprintf(
						"Replacing operator is available only for arrays, item '%s' is not array (used in '%s')",
						$key,
						$this->file,
					));
				}

				$key = substr($key, 0, -1);
				$val[Helpers::PREVENT_MERGING] = true;
			}

			if (is_array($val)) {
				$val = $this->process($val);

			} elseif ($val instanceof Neon\Entity) {
				if ($val->value === Neon\Neon::CHAIN) {
					$tmp = null;
					foreach ($this->process($val->attributes) as $st) {
						$tmp = new Statement(
							$tmp === null ? $st->getEntity() : [$tmp, ltrim(implode('::', (array) $st->getEntity()), ':')],
							$st->arguments,
						);
					}

					$val = $tmp;
				} else {
					$tmp = $this->process([$val->value]);
					if (is_string($tmp[0]) && str_contains($tmp[0], '?')) {
						throw new Nette\DI\InvalidConfigurationException("Operator ? is deprecated in config file (used in '$this->file')");
					}

					$val = new Statement($tmp[0], $this->process($val->attributes));
				}
			}

			$res[$key] = $val;
		}

		return $res;
	}


	/**
	 * Generates configuration in NEON format.
	 */
	public function dump(array $data): string
	{
		array_walk_recursive(
			$data,
			function (&$val): void {
				if ($val instanceof Statement) {
					$val = self::statementToEntity($val);
				}
			},
		);
		return "# generated by Nette\n\n" . Neon\Neon::encode($data, Neon\Neon::BLOCK);
	}


	private static function statementToEntity(Statement $val): Neon\Entity
	{
		array_walk_recursive(
			$val->arguments,
			function (&$val): void {
				if ($val instanceof Statement) {
					$val = self::statementToEntity($val);
				} elseif ($val instanceof Reference) {
					$val = '@' . $val->getValue();
				}
			},
		);

		$entity = $val->getEntity();
		if ($entity instanceof Reference) {
			$entity = '@' . $entity->getValue();
		} elseif (is_array($entity)) {
			if ($entity[0] instanceof Statement) {
				return new Neon\Entity(
					Neon\Neon::CHAIN,
					[
						self::statementToEntity($entity[0]),
						new Neon\Entity('::' . $entity[1], $val->arguments),
					],
				);
			} elseif ($entity[0] instanceof Reference) {
				$entity = '@' . $entity[0]->getValue() . '::' . $entity[1];
			} elseif (is_string($entity[0])) {
				$entity = $entity[0] . '::' . $entity[1];
			}
		}

		return new Neon\Entity($entity, $val->arguments);
	}


	private function firstClassCallableVisitor(Neon\Node $node): void
	{
		if ($node instanceof Neon\Node\EntityNode
			&& count($node->attributes) === 1
			&& $node->attributes[0]->key === null
			&& $node->attributes[0]->value instanceof Neon\Node\LiteralNode
			&& $node->attributes[0]->value->value === '...'
		) {
			$node->attributes[0]->value->value = Nette\DI\Resolver::getFirstClassCallable()[0];
		}
	}


	private function removeUnderscoreVisitor(Neon\Node $node): void
	{
		if (!$node instanceof Neon\Node\EntityNode) {
			return;
		}

		$index = false;
		foreach ($node->attributes as $i => $attr) {
			if ($attr->key !== null) {
				continue;
			}

			$attr->key = $index ? new Neon\Node\LiteralNode((string) $i) : null;

			if ($attr->value instanceof Neon\Node\LiteralNode && $attr->value->value === '_') {
				unset($node->attributes[$i]);
				$index = true;
			}
		}
	}


	private function convertAtSignVisitor(Neon\Node $node): void
	{
		if ($node instanceof Neon\Node\StringNode) {
			if (str_starts_with($node->value, '@@')) {
				trigger_error("There is no need to escape @ anymore, replace @@ with @ in: '$node->value' (used in $this->file)", E_USER_DEPRECATED);
			} else {
				$node->value = preg_replace('#^@#', '$0$0', $node->value); // escape
			}

		} elseif (
			$node instanceof Neon\Node\LiteralNode
			&& is_string($node->value)
			&& str_starts_with($node->value, '@@')
		) {
			trigger_error("There is no need to escape @ anymore, replace @@ with @ and put string in quotes: '$node->value' (used in $this->file)", E_USER_DEPRECATED);
		}
	}


	private function deprecatedParametersVisitor(Neon\Node $node): void
	{
		if (($node instanceof Neon\Node\StringNode || $node instanceof Neon\Node\LiteralNode)
			&& is_string($node->value)
			&& str_contains($node->value, '%parameters%')
		) {
			trigger_error('%parameters% is deprecated, use @container::getParameters() (in ' . $this->file . ')', E_USER_DEPRECATED);
		}
	}


	private function resolveConstants(Neon\Node $node): void
	{
		$items = match (true) {
			$node instanceof Neon\Node\ArrayNode => $node->items,
			$node instanceof Neon\Node\EntityNode => $node->attributes,
			default => null,
		};
		if ($items) {
			foreach ($items as $item) {
				if ($item->value instanceof Neon\Node\LiteralNode
					&& is_string($item->value->value)
                    && preg_match('#^([\w\\\\]*)::(\$[a-z_]|[A-Z])\w+$#D', $item->value->value)    //  constant/stat property
                ) {
                    $item->value->value = new Nette\PhpGenerator\Literal(ltrim($item->value->value, ':'));
				}
			}
		}
	}
}
