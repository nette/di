<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Definitions;

use Nette;
use Nette\DI\Definition;
use Nette\DI\Expressions\Reference;
use function sprintf;


/**
 * Definition of a locator service backed by a generated implementation of a user-defined interface with multiple get*()/create*() methods.
 */
final class LocatorDefinition extends Definition
{
	/** @var array<string, Reference> */
	private array $references = [];
	private ?string $tagged = null;


	public function setImplement(string $interface): static
	{
		if (!interface_exists($interface)) {
			throw new Nette\InvalidArgumentException(sprintf(
				"Service '%s': Interface '%s' not found.",
				$this->getName(throw: false) ?? '',
				$interface,
			));
		}

		$methods = (new \ReflectionClass($interface))->getMethods();
		if (!$methods) {
			throw new Nette\InvalidArgumentException(sprintf(
				"Service '%s': Interface %s must have at least one method.",
				$this->getName(throw: false) ?? '',
				$interface,
			));
		}

		foreach ($methods as $method) {
			if ($method->isStatic() || !(
				(preg_match('#^(get|create)$#', $method->name) && $method->getNumberOfParameters() === 1)
				|| (preg_match('#^(get|create)[A-Z]#', $method->name) && $method->getNumberOfParameters() === 0)
			)
			) {
				throw new Nette\InvalidArgumentException(sprintf(
					"Service '%s': Method %s::%s() does not meet the requirements: is create(\$name), get(\$name), create*() or get*() and is non-static.",
					$this->getName(throw: false) ?? '',
					$interface,
					$method->name,
				));
			}

			if ($method->getNumberOfParameters() === 0) {
				Nette\DI\Helpers::ensureClassType(
					Nette\Utils\Type::fromReflection($method),
					"return type of $interface::$method->name()",
					allowNullable: true,
				);
			}
		}

		return parent::setType($interface);
	}


	/** @deprecated Use getType() */
	public function getImplement(): ?string
	{
		return $this->getType();
	}


	/** @param  array<string, Reference|string>  $references */
	public function setReferences(array $references): static
	{
		$this->references = [];
		foreach ($references as $name => $ref) {
			$this->references[$name] = match (true) {
				$ref instanceof Reference => $ref,
				str_starts_with($ref, '@') => new Reference(substr($ref, 1)),
				default => Reference::fromType($ref),
			};
		}

		return $this;
	}


	/** @return array<string, Reference> */
	public function getReferences(): array
	{
		return $this->references;
	}


	public function setTagged(?string $tagged): static
	{
		$this->tagged = $tagged;
		return $this;
	}


	public function getTagged(): ?string
	{
		return $this->tagged;
	}


	public function resolveType(Nette\DI\Compiler\Resolver $resolver): void
	{
	}


	public function complete(Nette\DI\Compiler\Resolver $resolver): void
	{
		if ($this->tagged !== null) {
			$this->references = [];
			foreach ($resolver->getContainerBuilder()->findByTag($this->tagged) as $name => $tag) {
				if (isset($this->references[$tag])) {
					trigger_error(sprintf(
						"Service '%s': duplicated tag '%s' with value '%s'.",
						$this->getName(),
						$this->tagged,
						$tag,
					));
				}

				$this->references[$tag] = new Reference($name);
			}
		} elseif ($type = $this->getType()) {
			$this->validateReferences($type);
		}

		foreach ($this->references as $name => $ref) {
			$this->references[$name] = $ref->complete($resolver);
		}
	}


	/**
	 * Checks that references and methods pair up, leaving no method silently unimplemented.
	 * @param  class-string  $type
	 */
	private function validateReferences(string $type): void
	{
		$methods = $accepted = [];
		$generic = false;
		foreach ((new \ReflectionClass($type))->getMethods() as $rm) {
			if (!preg_match('#^(get|create)(.*)#', $rm->name, $m)) {
				continue;
			} elseif ($m[2] === '') {
				$generic = true;
				continue;
			}

			$methods[$rm->name] = $rm;
			foreach (self::getReferenceNames($m[2]) as $name) {
				$accepted[$name] = $rm->name;
			}
		}

		$matched = [];
		foreach (array_keys($this->references) as $name) {
			$methodName = $accepted[$name] ?? null;
			if ($methodName === null) {
				if (!$generic) {
					throw new Nette\DI\ServiceCreationException(sprintf(
						"Reference '%s' does not match any get*() or create*() method.",
						$name,
					));
				}
			} elseif (isset($matched[$methodName])) {
				throw new Nette\DI\ServiceCreationException(sprintf(
					"References '%s' and '%s' both match method %s::%s().",
					$matched[$methodName],
					$name,
					$type,
					$methodName,
				));

			} else {
				$matched[$methodName] = $name;
			}
		}

		foreach ($methods as $methodName => $rm) {
			if (!isset($matched[$methodName]) && !($rm->getReturnType()?->allowsNull() ?? false)) {
				throw new Nette\DI\ServiceCreationException(sprintf('Missing reference for method %s::%s().', $type, $methodName));
			}
		}
	}


	public function generateCode(Nette\DI\Compiler\PhpGenerator $generator): string
	{
		$type = $this->getType();
		assert($type !== null);

		$class = (new Nette\PhpGenerator\ClassType)
			->addImplement($type);

		$class->addMethod('__construct')
			->addPromotedParameter('container')
				->setPrivate()
				->setType($generator->getClassName());

		foreach ((new \ReflectionClass($type))->getMethods() as $rm) {
			if (!preg_match('#^(get|create)(.*)#', $rm->name, $m)) {
				continue;
			}
			$nullable = $rm->getReturnType()?->allowsNull() ?? false;

			$methodInner = $class->addMethod($rm->name)
				->setReturnType((string) Nette\Utils\Type::fromReflection($rm));

			if ($m[2] === '') {
				$class->addProperty('mapping', array_map(fn($item) => $item->getValue(), $this->references))
					->setPrivate();

				$methodInner->setBody(<<<'XX'
					if (!isset($this->mapping[$name])) {

					XX . "\t" . ($nullable ? 'return null;' : 'throw new Nette\DI\MissingServiceException("Service \'$name\' is not defined.");') . <<<'XX'

						}
						return $this->container->
						XX . $m[1] . 'Service($this->mapping[$name]);')
					->addParameter('name');

			} elseif ($reference = $this->findReference($m[2])) {
				$ref = $reference->getValue();
				if ($m[1] === 'get') {
					$methodInner->setBody('return $this->container->getService(?);', [$ref]);
				} else {
					$methodInner->setBody('return $this->container->?();', [Nette\DI\Container::getMethodName($ref)]);
				}
			} else {
				$methodInner->setBody(
					$nullable ? 'return null;' : 'throw new Nette\DI\MissingServiceException("Service is not defined.");',
				);
			}
		}

		return 'return new class ($this) ' . $class . ';';
	}


	private function findReference(string $suffix): ?Reference
	{
		foreach (self::getReferenceNames($suffix) as $name) {
			if (isset($this->references[$name])) {
				return $this->references[$name];
			}
		}

		return null;
	}


	/**
	 * Names under which a get*()/create*() method looks for its reference: the leading acronym
	 * lowercased (getPDO() => 'pdo'), plus the plain lcfirst() form kept for compatibility.
	 * @return string[]
	 */
	private static function getReferenceNames(string $suffix): array
	{
		$normalized = preg_replace_callback(
			'#^[A-Z]+(?=[A-Z][a-z]|[^A-Za-z]|$)|^[A-Z]#',
			fn($m) => strtolower($m[0]),
			$suffix,
		);
		return array_unique([$normalized, lcfirst($suffix)]);
	}
}
