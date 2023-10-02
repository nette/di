<?php
declare(strict_types=1);

namespace Nette\DI\Extensions;

use Nette;
use Nette\DI\Definitions\Statement;
use Nette\Schema;
use Nette\Schema\Expect;

/**
 * Based on ParametersSchemaExtension from PHPStan
 *     https://github.com/phpstan/phpstan-src/blob/6d523028e399c15dc77aec3affd2ea97ff735925/src/DependencyInjection/ParametersSchemaExtension.php
 * @property-read Statement[] $config
 */
final class SchemaExtension extends Nette\DI\CompilerExtension
{
	public function getConfigSchema(): Schema\Schema
	{
		return Expect::arrayOf(
			Expect::type(Statement::class)
		);
	}


	public function loadConfiguration(): void
	{
		$schema = Expect::structure($this->processArray($this->config))
			->otherItems(Expect::mixed());

		$this->validateSchema($schema, $this->compiler->getConfig());
	}


	/**
	 * @param mixed $value
	 * @return mixed
	 */
	private function process($value)
	{
		if ($value instanceof Statement) {
			return $this->processStatement($value);
		}

		if (is_array($value)) {
			return $this->processArray($value);
		}

		return $value;
	}


	private function processStatement(Statement $statement): Schema\Schema
	{
		if ($statement->entity === 'schema') {
			$arguments = [];
			foreach ($statement->arguments as $value) {
				if (!$value instanceof Statement) {
					$valueType = gettype($value);
					throw new Nette\InvalidArgumentException("schema() should contain another statement(), $valueType given.");
				}

				$arguments[] = $value;
			}

			if (count($arguments) === 0) {
				throw new Nette\InvalidArgumentException('schema() should have at least one argument.');
			}

			return $this->buildSchemaFromStatements($arguments);
		}

		return $this->buildSchemaFromStatements([$statement]);
	}


	/**
	 * @param mixed[] $array
	 * @return mixed[]
	 */
	private function processArray(array $array): array
	{
		return array_map(
			function ($value) {
				return $this->process($value);
			},
			$array
		);
	}


	/**
	 * @param Statement[] $statements
	 */
	private function buildSchemaFromStatements(array $statements): Schema\Schema
	{
		$schema = null;
		foreach ($statements as $statement) {
			$processedArguments = array_map(
				function ($argument) {
					return $this->process($argument);
				},
				$statement->arguments
			);

			if ($schema === null) {
				$methodName = $statement->getEntity();
				assert(is_string($methodName));

				$schema = Expect::{$methodName}(...$processedArguments);
				assert(
					$schema instanceof Schema\Elements\Type ||
					$schema instanceof Schema\Elements\AnyOf ||
					$schema instanceof Schema\Elements\Structure
				);

				$schema->required();
			} else {
				$schema->{$statement->getEntity()}(...$processedArguments);
			}
		}

		return $schema;
	}


	/**
	 * @param mixed[] $config
	 */
	private function validateSchema(Schema\Elements\Structure $schema, array $config): void
	{
		$processor = new Schema\Processor;
		try {
			$processor->process($schema, $config);
		} catch (Schema\ValidationException $e) {
			throw new Nette\DI\InvalidConfigurationException($e->getMessage());
		}
		foreach ($processor->getWarnings() as $warning) {
			trigger_error($warning, E_USER_DEPRECATED);
		}
	}
}
