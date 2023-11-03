%A%
class Container extends Nette\DI\Container
{
%A%

	public function __construct(array $params = [])
	{
		parent::__construct($params);
		$this->parameters += [
			'static' => 123,
			'expr' => null,
			'dynamic' => null,
			'dynamicArray' => null,
			'arrayExpr' => ['expr' => null],
			'arrayExpr2' => ['expr' => null],
			'arrayDynamic' => ['dynamic' => null, 'inner' => null],
			'arrayMix' => ['expr' => null, 'dynamic' => null],
			'refStatic' => 123,
			'refDynamic' => null,
			'refDynamic2' => null,
			'refExpr' => null,
			'refArrayE1' => ['expr' => null],
			'refArrayE2' => null,
			'refArrayD1' => ['dynamic' => null, 'inner' => null],
			'refArrayD2' => null,
			'refArrayD3' => null,
		];
	}


	public function createService01(): Service
	{
		return new Service(
			123,
			trim(' a '),
			($this->parameters['dynamic'] ?? 123),
			($this->parameters['dynamic'] ?? 123)['foo'],
			['expr' => trim(' a ')],
			trim(' a '),
			['dynamic' => ($this->parameters['dynamic'] ?? 123), 'inner' => ($this->parameters['dynamic'] ?? 123)['foo']],
			($this->parameters['dynamic'] ?? 123),
			($this->parameters['dynamic'] ?? 123)['foo']%a?%
		);
	}

%A%
}
