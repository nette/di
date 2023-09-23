// source: array

/** @noinspection PhpParamsInspection,PhpMethodMayBeStaticInspection */

declare(strict_types=1);

class Container extends Nette\DI\Container
{
	protected $types = ['container' => 'Nette\DI\Container'];
	protected $aliases = [];
	protected $wiring = ['Nette\DI\Container' => [['container']], 'stdClass' => [['01', 'name']]];


	public function __construct(array $params = [])
	{
		parent::__construct($params);
		$this->parameters += [];
	}


	public function createService01(): stdClass
	{
		return new stdClass;
	}


	public function createServiceContainer(): Container
	{
		return $this;
	}


	public function createServiceName(): stdClass
	{
		return new stdClass;
	}


	public function initialize()
	{
	}
}
