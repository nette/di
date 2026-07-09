<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI\Compiler;

use Nette\DI\CompilerExtension;
use Nette\DI\Container;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definition;
use Nette\DI\Definitions\Statement;
use Nette\DI\Expression;
use Nette\DI\ServiceCreationException;
use Nette\PhpGenerator as Php;
use function is_object, sprintf;


/**
 * Container PHP code generator.
 */
class PhpGenerator
{
	private ?string $className = null;


	public function __construct(
		private readonly ContainerBuilder $builder,
	) {
	}


	/**
	 * Generates PHP classes. First class is the container.
	 */
	public function generate(string $className): Php\ClassType
	{
		$this->className = $className;
		$class = new Php\ClassType($this->className);
		$class->setExtends(Container::class);
		$manipulator = new Php\ClassManipulator($class);
		$manipulator->inheritMethod('__construct')
			->addBody('parent::__construct($params);');

		foreach ($this->builder->exportMeta() as $key => $value) {
			$manipulator->inheritProperty($key)
				->setComment(null)
				->setValue($value);
		}

		$definitions = $this->builder->getDefinitions();
		ksort($definitions);

		foreach ($definitions as $def) {
			$class->addMember($this->generateMethod($def));
		}

		$class->getMethod(Container::getMethodName(ContainerBuilder::ThisContainer))
			->setBody('return $this;');

		$manipulator->inheritMethod('initialize');

		return $class;
	}


	public function toString(Php\ClassType $class): string
	{
		return <<<'XX'
			/** @noinspection PhpParamsInspection,PhpMethodMayBeStaticInspection */

			declare(strict_types=1);


			XX . $class->__toString();
	}


	public function addInitialization(Php\ClassType $class, CompilerExtension $extension): void
	{
		$closure = $extension->getInitialization();
		if ($closure->getBody()) {
			$class->getMethod('initialize')
				->addBody('// ' . $extension->prefix(''))
				->addBody("($closure)();");
		}
	}


	public function generateMethod(Definition $def): Php\Method
	{
		$name = $def->getName();
		assert($name !== null);
		try {
			$method = new Php\Method(Container::getMethodName($name));
			$method->setPublic();
			$method->setReturnType($def->getType());
			$def->generateMethod($method, $this);
			return $method;

		} catch (\Throwable $e) {
			throw new ServiceCreationException("Service '$name': " . $e->getMessage(), 0, $e);
		}
	}


	/** @deprecated use Statement::generateCode() */
	public function formatStatement(Statement $statement): string
	{
		return $statement->generateCode($this);
	}


	/**
	 * Formats PHP statement.
	 * @param  array<mixed>  $args
	 * @internal
	 */
	public function formatPhp(string $statement, array $args): string
	{
		return (new Php\Dumper)->format($statement, ...$this->convertArguments($args));
	}


	/**
	 * @param  array<mixed>  $args
	 * @return array<mixed>
	 */
	public function convertArguments(array $args): array
	{
		array_walk_recursive($args, function (&$val): void {
			if ($val instanceof Expression) {
				$val = new Php\Literal($val->generateCode($this));

			} elseif (
				is_object($val)
				&& !$val instanceof Php\Literal && !$val instanceof \DateTimeInterface
				&& (new \ReflectionObject($val))->getProperties(\ReflectionProperty::IS_PRIVATE | \ReflectionProperty::IS_PROTECTED)
			) {
				trigger_error(sprintf('Nette DI: suspicious dumping of objects %s when generating the container', $val::class));
			}
		});
		return $args;
	}


	public function getClassName(): ?string
	{
		return $this->className;
	}
}
