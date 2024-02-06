// source: array

/** @noinspection PhpParamsInspection,PhpMethodMayBeStaticInspection */

declare(strict_types=1);

class Container extends Nette\DI\Container
{
	/** @var string[]  services name => type (complete list of available services) */
	protected $types = ['container' => 'Nette\DI\Container'];

	/** @var string[]  alias => service name */
	protected $aliases = [];

	/** @var array[]  type => level => services */
	protected $wiring = ['Nette\DI\Container' => [['container']], 'stdClass' => [['01', 'name']]];


	public function __construct(array $params = [])
	{
		parent::__construct($params);
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


	public function initialize(): void
	{
	}


	protected function getStaticParameters(): array
	{
		return [];
	}
}
