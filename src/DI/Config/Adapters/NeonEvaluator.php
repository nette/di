<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Config\Adapters;

use Nette;
use Nette\DI\Definitions\Statement;
use Nette\DI\{Expression, Helpers};
use Nette\DI\Expressions\{ArgumentPlaceholder, PartialCall, PropertyAccess};
use Nette\Neon;
use Nette\Neon\{Nodes, Place};
use function count, is_array, is_string, sprintf;


/**
 * Configuration from a file read by nette/neon 4: entities and chains become statements, a key ending with !
 * prevents merging, constants, escaped @ in strings, omitted arguments and first-class callables.
 * @internal
 */
final class NeonEvaluator extends Neon\Evaluator
{
	private const PreventMergingSuffix = '!';


	public function __construct(
		private readonly string $file,
	) {
	}


	protected function evaluateLiteral(Nodes\LiteralNode $node, Place $place): mixed
	{
		$value = parent::evaluateLiteral($node, $place);
		if (is_string($value) && str_starts_with($value, '@@')) {
			trigger_error(
				"There is no need to escape @ anymore, replace @@ with @ and put string in quotes: '$value' (used in $this->file)",
				E_USER_DEPRECATED,
			);
		}
		return $place === Place::Key ? self::removePreventMergingSuffix($value) : $value;
	}


	protected function evaluateString(Nodes\StringNode $node, Place $place): mixed
	{
		$value = (string) parent::evaluateString($node, $place);
		if (str_starts_with($value, '@@')) {
			trigger_error("There is no need to escape @ anymore, replace @@ with @ in: '$value' (used in $this->file)", E_USER_DEPRECATED);
		} else {
			$value = preg_replace('#^@#', '$0$0', $value); // escape
		}
		return $place === Place::Key ? self::removePreventMergingSuffix($value) : $value;
	}


	protected function evaluateEntity(Nodes\EntityNode $node, Place $place): Expression
	{
		[$head, $attributes] = $this->evaluateParts($node);
		if (is_string($head) && str_contains($head, '::')) { // Class::method(), @service::member::method(), ::function()
			$members = explode('::', $head);
			$target = Helpers::createTarget(array_shift($members));
			$name = array_pop($members);
			return self::createCall(self::accessMembers($target, $members), $name, $attributes);

		} elseif ($attributes === [ArgumentPlaceholder::Variadic]) {
			throw new Nette\DI\InvalidConfigurationException("Cannot create closure for '$head(...)' in config file (used in '$this->file')");
		}

		return new Statement($head, $attributes);
	}


	/**
	 * Each link continues with the members of the result of the links before it; the last member of a link
	 * is called when the link has parentheses, otherwise it is read.
	 */
	protected function evaluateChain(Nodes\EntityChainNode $node): Expression
	{
		$expression = null;
		foreach ($node->entities as $link) {
			if ($expression === null) {
				$expression = $this->evaluateEntity($link, Place::ChainLink);
				continue;
			}

			[$head, $attributes] = $this->evaluateParts($link);
			if (!is_string($head) || !str_starts_with($head, '::')) { // text glued after the parentheses, such as foo(x);
				$expression = self::createCall($expression, ltrim((string) $head, ':'), $attributes);
				continue;
			}

			$members = explode('::', substr($head, 2));
			$name = array_pop($members);
			$expression = self::accessMembers($expression, $members);
			$expression = $link->attributes === null
				? Helpers::accessMember($expression, $name)
				: self::createCall($expression, $name, $attributes);
		}
		return $expression;
	}


	protected function evaluateItem(Nodes\ArrayItemNode $item): mixed
	{
		$value = parent::evaluateItem($item);
		if ($item->value instanceof Nodes\LiteralNode && is_string($value)) {
			if (preg_match('#^[\w\\\]*::[A-Z]\w+$#D', $value)) {
				$value = defined(ltrim($value, ':'))
					? constant(ltrim($value, ':'))
					: throw new Nette\DI\InvalidConfigurationException("Constant '$value' is not defined (used in '$this->file')");
			} elseif (preg_match('#^([\w\\\]+)::\$(\w+)$#D', $value, $m)) {
				$value = new PropertyAccess($m[1], $m[2], static: true);
			}
		}

		$key = match (true) {
			$item->key instanceof Nodes\LiteralNode => $item->key->token->text,
			$item->key instanceof Nodes\StringNode => parent::evaluateString($item->key, Place::Key),
			default => null,
		};
		if (is_string($key) && str_ends_with($key, self::PreventMergingSuffix)) {
			if (is_array($value) || $value === null) {
				$value[Nette\DI\Config\Helpers::PREVENT_MERGING] = true;
			} else {
				$value = new Nette\DI\Config\Replacement($value);
			}
		}
		return $value;
	}


	/**
	 * The head and the attributes of an entity; an argument `_` is omitted, and the arguments after it keep
	 * their index among all the arguments.
	 * @return array{mixed, array<mixed>}
	 */
	private function evaluateParts(Nodes\EntityNode $node): array
	{
		$head = $this->evaluate($node->value, Place::EntityHead);
		if (is_string($head) && str_contains($head, '?')) {
			throw new Nette\DI\InvalidConfigurationException("Operator ? is deprecated in config file (used in '$this->file')");
		}

		$items = $node->attributes->items ?? [];
		if (count($items) === 1 && !$items[0]->key && self::isLiteral($items[0]->value, '...')) {
			return [$head, [ArgumentPlaceholder::Variadic]];
		}

		$attributes = [];
		$omitted = false;
		foreach ($items as $i => $item) {
			if (!$item->key && self::isLiteral($item->value, '_')) {
				$omitted = true;
			} elseif ($item->key) {
				$attributes[$this->evaluate($item->key, Place::Key)] = $this->evaluateItem($item);
			} elseif ($omitted) {
				$attributes[$i] = $this->evaluateItem($item);
			} else {
				$attributes[] = $this->evaluateItem($item);
			}
		}

		array_walk_recursive($attributes, function (mixed $value, int|string $key): void {
			if ($value instanceof Nette\DI\Config\Replacement) {
				throw new Nette\DI\InvalidConfigurationException(sprintf(
					"Replacing operator is available only for arrays, item '%s!' is not array (used in '%s')",
					$key,
					$this->file,
				));
			}
		});
		return [$head, $attributes];
	}


	/** @param  list<string>  $members */
	private static function accessMembers(Expression|string $target, array $members): Expression|string
	{
		foreach ($members as $member) {
			$target = Helpers::accessMember($target, $member);
		}
		return $target;
	}


	/**
	 * A member with parentheses: a call, or the assignment to a $property with the value in parentheses.
	 * @param  array<mixed>  $attributes
	 */
	private static function createCall(Expression|string $target, string $name, array $attributes): Expression
	{
		return match (true) {
			$attributes === [ArgumentPlaceholder::Variadic] => new PartialCall($target === '' ? null : $target, $name),
			str_starts_with($name, '$') && !$attributes => Helpers::accessMember($target, $name),
			str_starts_with($name, '$') && count($attributes) === 1 => Helpers::assignMember($target, $name, reset($attributes)),
			default => new Statement([$target, $name], $attributes),
		};
	}


	private static function removePreventMergingSuffix(mixed $key): mixed
	{
		return is_string($key) && str_ends_with($key, self::PreventMergingSuffix)
			? substr($key, 0, -1)
			: $key;
	}


	private static function isLiteral(?Nodes\ValueNode $node, string $text): bool
	{
		return $node instanceof Nodes\LiteralNode && $node->token->text === $text;
	}
}
