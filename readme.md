Nette Dependency Injection
==========================

[![Downloads this Month](https://img.shields.io/packagist/dm/nette/di.svg)](https://packagist.org/packages/nette/di)
[![Build Status](https://travis-ci.org/nette/di.svg?branch=v2.3)](https://travis-ci.org/nette/di)

Purpose of the Dependecy Injection (DI) is to free classes from the responsibility for obtaining objects that they need for its operation (these objects are called **services**). To pass them these services on their instantiation instead.

Class `Nette\DI\Container` is a flexible implementation of the universal DI container. It ensures automatically, that instance of services are created only once.

Names of factory methods follow an uniform convention, they consist of the prefix `createService` + name of the service starting with first letter upper-cased. If they are not supposed to be accesible from outside, it is possible to lower their visibility to `protected`. Note that the container has already defined the field `$parameters` for user parameters.

```php
class MyContainer extends Nette\DI\Container
{

	protected function createServiceConnection()
	{
		return new Nette\Database\Connection(
			$this->parameters['dsn'],
			$this->parameters['user'],
			$this->parameters['password']
		);
	}

	protected function createServiceArticle()
	{
		return new Article($this->connection);
	}

}
```

Now we create an instance of the container and pass parameters:

```php
$container = new MyContainer(array(
	'dsn' => 'mysql:',
	'user' => 'root',
	'password' => '***',
));
```

We get the service by calling the `getService` method or by a shortcut:

```php
$article = $container->getService('article');
```

As have been said, all services are created in one container only once, but it would be more useful, if the container was creating always a new instance of `Article`. It could be achieved easily: Instead of the factory for the service `article` we'll create an ordinary method `createArticle`:

```php
class MyContainer extends Nette\DI\Container
{

	function createServiceConnection()
	{
		return new Nette\Database\Connection(
			$this->parameters['dsn'],
			$this->parameters['user'],
			$this->parameters['password']
		);
	}

	function createArticle()
	{
		return new Article($this->connection);
	}

}

$container = new MyContainer(...);

$article = $container->createArticle();
```

From the call of `$container->createArticle()` is evident, that a new object is always created. It is then a programmer's convention.
