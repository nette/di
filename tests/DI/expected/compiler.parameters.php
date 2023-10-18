%A%
class Container extends Nette\DI\Container
{
%A%

	public function __construct(array $params = [])
	{
		parent::__construct($params);
	}


	public function createService01(): Service
	{
		return new Service(
			123,
			trim(' a '),
			$this->getParameter('dynamic'),
			$this->getParameter('dynamic')['foo'],
			['expr' => trim(' a ')],
			trim(' a '),
			['dynamic' => $this->getParameter('dynamic')],
			$this->getParameter('dynamic'),
			$this->getParameter('dynamic')['foo']%a?%
		);
	}

%A%

	protected function getStaticParameters(): array
	{
		return ['static' => 123, 'refStatic' => 123];
	}


	protected function getDynamicParameter($key)
	{
		switch (true) {
			case $key === 'dynamic': return 123;
			case $key === 'expr': return trim(' a ');
			case $key === 'arrayExpr': return ['expr' => trim(' a ')];
			case $key === 'arrayExpr2': return ['expr' => trim(' a ')];
			case $key === 'arrayDynamic': return ['dynamic' => $this->getParameter('dynamic')];
			case $key === 'refDynamic': return $this->getParameter('dynamic');
			case $key === 'refDynamic2': return $this->getParameter('dynamic')['foo'];
			case $key === 'refExpr': return trim(' a ');
			case $key === 'refArrayE1': return ['expr' => trim(' a ')];
			case $key === 'refArrayE2': return trim(' a ');
			case $key === 'refArrayD1': return ['dynamic' => $this->getParameter('dynamic')];
			case $key === 'refArrayD2': return $this->getParameter('dynamic');
			case $key === 'refArrayD3': return $this->getParameter('dynamic')['foo'];
			default: return parent::getDynamicParameter($key);
		};
	}


	public function getParameters(): array
	{
		array_map(function ($key) { try { $this->getParameter($key); } catch (\Throwable $e) { $this->parameters[$key] = "unable to resolve"; } }, [
			'dynamic',
			'expr',
			'arrayExpr',
			'arrayExpr2',
			'arrayDynamic',
			'refDynamic',
			'refDynamic2',
			'refExpr',
			'refArrayE1',
			'refArrayE2',
			'refArrayD1',
			'refArrayD2',
			'refArrayD3',
		]);
		return parent::getParameters();
	}
}
