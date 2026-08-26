<?php declare(strict_types=1);

/**
 * Test: FactoryDefinition: the produced service autowires itself in its setup, but never in its creator
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Widget
{
	public ?Widget $self = null;


	public function setSelf(self $widget): void
	{
		$this->self = $widget;
	}
}

class SelfInjectingWidget extends Widget
{
	public function __construct(Widget $widget)
	{
	}


	public static function create(Widget $widget): static
	{
		return new static($widget);
	}
}

interface WidgetFactory
{
	public function create(): Widget;
}


test('setup autowires the produced service itself', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addFactoryDefinition('factory')
		->setImplement(WidgetFactory::class)
		->getResultDefinition()
			->setCreator(Widget::class)
			->addSetup('setSelf');

	$container = createContainer($builder);

	$widget = $container->getService('factory')->create();
	Assert::same($widget, $widget->self);
});


test('constructor never receives the produced service itself', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addFactoryDefinition('factory')
		->setImplement(WidgetFactory::class)
		->getResultDefinition()
			->setCreator(SelfInjectingWidget::class);

	Assert::exception(
		fn() => $builder->complete(),
		DI\ServiceCreationException::class,
		"Service 'factory' (type of WidgetFactory): Service of type SelfInjectingWidget: Service of type Widget required by \$widget in SelfInjectingWidget::__construct() not found. Did you add it to configuration file?",
	);
});


test('factory method never receives the produced service itself', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addFactoryDefinition('factory')
		->setImplement(WidgetFactory::class)
		->getResultDefinition()
			->setCreator([SelfInjectingWidget::class, 'create']);

	Assert::exception(
		fn() => $builder->complete(),
		DI\ServiceCreationException::class,
		"Service 'factory' (type of WidgetFactory): Service of type SelfInjectingWidget: Service of type Widget required by \$widget in SelfInjectingWidget::create() not found. Did you add it to configuration file?",
	);
});
