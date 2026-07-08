// source: array

/** @noinspection PhpParamsInspection,PhpMethodMayBeStaticInspection */

declare(strict_types=1);

class Container extends Nette\DI\Container
{
	protected array $aliases = [];
	protected array $wiring = ['Nette\DI\Container' => [['container']], 'stdClass' => [['01', 'name']]];


	/**
	 * @param  mixed[]  $params
	 */
	public function __construct(array $params = [])
	{
		parent::__construct($params);
	}


	public function createService01(): stdClass
	{
		return new stdClass;
	}


	public function createServiceContainer(): Nette\DI\Container
	{
		return $this;
	}


	public function createServiceName(): stdClass
	{
		return new stdClass;
	}


	public function initialize(): void
	{
	}


	/**
	 * @return mixed[]
	 */
	protected function getStaticParameters(): array
	{
		return [];
	}
}
