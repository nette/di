<?php declare(strict_types=1);

/**
 * Test: golden lock of code generated for the whole expression matrix (see docs/expression-roadmap.md).
 * Compares only per-service outputs of generateCode(), not the whole container, so that
 * changes of unrelated boilerplate do not invalidate the lock. Never edit the expected
 * file by hand: review the .actual diff and commit it.
 */

use Nette\DI;
use Nette\DI\Definitions\Statement;
use Nette\DI\Expressions\PartialCall;
use Nette\DI\Expressions\Reference;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class GoldDep
{
}


class GoldConsumer
{
	public function __construct(mixed $a = null, mixed $b = null, mixed $c = null)
	{
	}
}


class GoldFactory
{
	public static function create(mixed $x = null): GoldDep
	{
		return new GoldDep;
	}
}


class GoldSvc
{
	public const VERSION = '1.0';

	public $prop;
	public $decorated;
	public array $items = [];


	public static function init(mixed $x = null): void
	{
	}


	public function identity(mixed $x = null): mixed
	{
		return $x;
	}


	public function chain(): static
	{
		return $this;
	}


	public function withDep(GoldDep $dep, string $text): static
	{
		return $this;
	}


	public function makeDep(): GoldDep
	{
		return new GoldDep;
	}
}


// F9 + F12: PHP literal and expression objects passed through ContainerBuilder API
class GoldExtension extends DI\CompilerExtension
{
	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();

		// F9 PHP literal (contains ?) - exists only via API, in NEON the ? operator is deprecated
		$builder->addDefinition('api1')
			->setCreator(GoldSvc::class)
			->addSetup('$service->identity(?, ?)', [1, 2])                          // F9xK3 literal setup, 2 placeholders
			->setAutowired(false);

		$builder->addDefinition('apiLiteral')
			->setType(GoldDep::class)
			->setCreator('GoldFactory::create(?)', [7])                             // F9xK1 literal creator
			->setAutowired(false);

		$apiDep = $builder->addDefinition('apiDep')
			->setType(GoldDep::class)
			->setAutowired(false);

		$builder->addDefinition('api2')
			->setCreator(GoldConsumer::class, [
				$apiDep,                                                            // F12 Definition object
				new Statement([new Reference('svc'), 'identity'], [1]),             // F12 Statement object
				new PartialCall(null, 'trim'),                                      // F12 PartialCall object
			])
			->setAutowired(false);
	}
}


$compiler = new DI\Compiler;
$compiler->addExtension('decorator', new DI\Extensions\DecoratorExtension);
$compiler->addExtension('gold', new GoldExtension);
$compiler->addConfig((new DI\Config\Loader)->load(__DIR__ . '/files/expressions.golden.neon'));
$compiler->compile();

$builder = $compiler->getContainerBuilder();
$generator = new DI\PhpGenerator($builder);

$output = '';
$definitions = $builder->getDefinitions();
ksort($definitions);
foreach ($definitions as $name => $def) {
	if ($name === 'container') {
		continue;
	}
	$output .= "== $name\n" . $def->generateCode($generator) . "\n\n";
}

Assert::matchFile(__DIR__ . '/expected/expressions.golden.txt', $output);
