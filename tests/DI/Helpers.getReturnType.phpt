<?php

/**
 * Test: Nette\DI\Helpers::getReturnType
 */

declare(strict_types=1);

use Nette\DI\Helpers;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/fixtures/Helpers.getReturnType.php';


Assert::null(Helpers::getReturnTypeAnnotation(new ReflectionMethod(NS\A::class, 'noType')));

Assert::same('Test\B', (string) Helpers::getReturnTypeAnnotation(new ReflectionMethod(NS\A::class, 'annotationClassType')));

Assert::same('Test\B', (string) Helpers::getReturnTypeAnnotation(new ReflectionMethod(NS\A::class, 'annotationUnionType')));

Assert::same('string', (string) Helpers::getReturnTypeAnnotation(new ReflectionMethod(NS\A::class, 'annotationNativeType')));

Assert::same('NS\A', (string) Helpers::getReturnTypeAnnotation(new ReflectionMethod(NS\A::class, 'annotationSelfType')));

Assert::same('NS\A', (string) Helpers::getReturnTypeAnnotation(new ReflectionMethod(NS\A::class, 'annotationStaticType')));

// class name expanding is NOT supported for global functions
Assert::same('B', (string) Helpers::getReturnTypeAnnotation(new ReflectionFunction('NS\annotationClassType')));
