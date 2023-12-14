<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Config\Adapters;

use Nette;
use Nette\Neon;
use Nette\Neon\Node;
use function count, is_string;


/**
 * Values of a configuration file read by nette/neon 3: constants, escaped @ in strings, omitted arguments
 * and first-class callables. The entities stay entities.
 * @internal
 */
final readonly class NeonVisitors
{
	public function __construct(
		private string $file,
	) {
	}


	public function decode(string $input): mixed
	{
		$decoder = new Neon\Decoder;
		$node = $decoder->parseToNode($input);
		$traverser = new Neon\Traverser;
		$node = $traverser->traverse($node, $this->firstClassCallableVisitor(...));
		$node = $traverser->traverse($node, $this->removeUnderscoreVisitor(...));
		$node = $traverser->traverse($node, $this->convertAtSignVisitor(...));
		$node = $traverser->traverse($node, $this->resolveConstantsVisitor(...));
		return $node->toValue();
	}


	private function firstClassCallableVisitor(Node $node): void
	{
		if ($node instanceof Node\EntityNode
			&& count($node->attributes) === 1
			&& $node->attributes[0]->key === null
			&& $node->attributes[0]->value instanceof Node\LiteralNode
			&& $node->attributes[0]->value->value === '...'
		) {
			$node->attributes[0]->value->value = Nette\DI\Expressions\ArgumentPlaceholder::Variadic;
		}
	}


	private function removeUnderscoreVisitor(Node $node): void
	{
		if (!$node instanceof Node\EntityNode) {
			return;
		}

		$index = false;
		foreach ($node->attributes as $i => $attr) {
			if ($attr->key !== null) {
				continue;
			}

			$attr->key = $index ? new Node\LiteralNode((string) $i) : null;

			if ($attr->value instanceof Node\LiteralNode && $attr->value->value === '_') {
				unset($node->attributes[$i]);
				$index = true;
			}
		}

		$node->attributes = array_values($node->attributes);
	}


	private function convertAtSignVisitor(Node $node): void
	{
		if ($node instanceof Node\StringNode) {
			if (str_starts_with($node->value, '@@')) {
				trigger_error(
					"There is no need to escape @ anymore, replace @@ with @ in: '$node->value' (used in $this->file)",
					E_USER_DEPRECATED,
				);
			} else {
				$node->value = preg_replace('#^@#', '$0$0', $node->value); // escape
			}

		} elseif (
			$node instanceof Node\LiteralNode
			&& is_string($node->value)
			&& str_starts_with($node->value, '@@')
		) {
			trigger_error(
				"There is no need to escape @ anymore, replace @@ with @ and put string in quotes: '$node->value' (used in $this->file)",
				E_USER_DEPRECATED,
			);
		}
	}


	private function resolveConstantsVisitor(Node $node): void
	{
		$items = match (true) {
			$node instanceof Node\ArrayNode => $node->items,
			$node instanceof Node\EntityNode => $node->attributes,
			default => null,
		};
		if ($items) {
			foreach ($items as $item) {
				if ($item->value instanceof Node\LiteralNode
					&& is_string($item->value->value)
					&& preg_match('#^([\w\\\]*)::[A-Z]\w+$#D', $item->value->value)
					&& defined(ltrim($item->value->value, ':'))
				) {
					$item->value->value = constant(ltrim($item->value->value, ':'));
				}
			}
		}
	}
}
