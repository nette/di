# Upgrading nette/di

## 3.2.8

- **Renamed** `Nette\DI\Definitions\Definition` → `Nette\DI\Definition`; the old name still works as an alias (ab66fdcd)
- **Changed** `Nette\DI\ContainerBuilder::addDefinition()` now throws `Nette\InvalidArgumentException` for a service name containing `__`, which is reserved for encoding dots in method names; previously such a name was accepted (b2815346)
- **Changed** a locator defined with `implement` now throws `Nette\DI\ServiceCreationException` at compile time when a reference matches no `get*()` or `create*()` method, when two references match one method, or when a method with a non-nullable return type has no reference; previously the container compiled and the method failed only when called; add the missing reference or make the return type nullable (e92a7a26)

## 3.2.7

- **Replaced** reflection over the generated container's `createService*()` methods with `Nette\DI\Container::getServiceDescriptors()`: introspection tools should call the new method, which returns `Nette\DI\ServiceDescriptor` objects without instantiating services (ee27e39e)
- **Changed** `Nette\DI\ContainerBuilder::addDefinition()` now throws `Nette\InvalidArgumentException` for the service name `self`, which is reserved for a reference to the current service; previously such a service could be registered (e12db4ef)
- **Changed** `Nette\DI\Container::removeService()` now also unregisters a factory registered through `Nette\DI\Container::addService()`; previously only a created instance was dropped and the service could be created again (8613a670)
- **Changed** `Nette\DI\ContainerLoader::load()` with auto-rebuild enabled now declares a freshly named container class on every rebuild; only the first one is bound to `Nette\DI\ContainerLoader::getClassName()` through `class_alias()`; affects code deriving the container class name (f43c07ac)
- **Config** the `tagged` argument in entity notation now throws `Nette\DI\InvalidConfigurationException` when combined with other arguments; previously it was silently treated as `references` (669e50ab)

## 3.2.6

- **Signature** `$builder` of `Nette\DI\Compiler::__construct()` is no longer nullable and defaults to a new `Nette\DI\ContainerBuilder`; passing `null` explicitly now throws `TypeError` (93ff0d06)
- **Changed** `Nette\DI\ContainerLoader::generate()` now uses a generator callback's empty-string result as-is; previously it fell back to `Nette\DI\Compiler::compile()`, which only a `null` result still triggers; affects subclasses overriding it (93ff0d06)
- **Changed** `Nette\DI\Extensions\DecoratorExtension` now silently skips `setup` entries whose matched definition is not a `Nette\DI\Definitions\ServiceDefinition`; previously this ended with a fatal error (37bb2c3d)

## 3.2.4

- **Deprecated** passing arguments to a service reference in configuration, as in `@service(args)`: pass the arguments where the service is defined; using it triggers `E_USER_DEPRECATED` (b1cf83bf)

## 3.2.2

- **Removed** `Nette\DI\Container::$types`: read the service type with `Nette\DI\Container::getServiceType()`; affects subclasses and hand-written containers (79fd5be5)
- **Signature** `Nette\DI\Container::getMethodName()` is now `final`; affects subclasses overriding it (1fa0bc43)
- **Signature** `Nette\DI\ContainerBuilder::$parameters` is now natively typed `array`; assigning anything else throws `TypeError` (1c247c0a)
- **Changed** `Nette\DI\Container::getByType()` now throws `Nette\DI\MissingServiceException` when the type is not found and the container holds a service added at runtime; previously a `ReflectionException` escaped (fe3ed082)
- **Changed** a locator interface implemented with a `create($name)` method no longer triggers `E_USER_DEPRECATED`; the pattern is supported again (deprecated since 3.2.0) (c1d4186c)

## 3.2.1

- **Changed** `Nette\DI\Compiler::loadDefinitionsFromConfig()` now expands `%param%` placeholders in the passed configuration against the builder parameters; previously they were passed through unexpanded (eba66c96)

## 3.2.0

- **Removed** `Nette\DI\Definitions\Definition::getImplement()` (deprecated since 3.0.0): test with `instanceof Nette\DI\Definitions\FactoryDefinition` or `Nette\DI\Definitions\AccessorDefinition` (2b9638de)
- **Removed** `Nette\DI\Definitions\Definition::isDynamic()` (deprecated since 3.0.0): test with `instanceof Nette\DI\Definitions\ImportedDefinition` (2b9638de)
- **Removed** `Nette\DI\Definitions\ImportedDefinition::isDynamic()` (deprecated since 3.0.0): test with `instanceof Nette\DI\Definitions\ImportedDefinition` (2b9638de)
- **Removed** `Nette\DI\Definitions\FactoryDefinition::$parameters` (deprecated since 3.1.0): parameters are derived from the implemented interface (95636a7e)
- **Removed** `Nette\DI\Definitions\FactoryDefinition::getParameters()` (deprecated since 3.1.0) (95636a7e)
- **Removed** `Nette\DI\Definitions\FactoryDefinition::setParameters()` (deprecated since 3.1.0) (95636a7e)
- **Removed** `Nette\DI\PhpGenerator::convertParameters()` (95636a7e)
- **Removed** `Nette\DI\Config\Loader::save()` (deprecated since 3.1.0): render the configuration with an adapter and write the file yourself (2b9638de)
- **Renamed** `Nette\DI\Extensions\ConstantsExtension` → `Nette\Bootstrap\Extensions\ConstantsExtension`; the old name was removed (deprecated since 3.1.0) (2b9638de)
- **Renamed** `Nette\DI\Extensions\PhpExtension` → `Nette\Bootstrap\Extensions\PhpExtension`; the old name was removed (deprecated since 3.1.0) (2b9638de)
- **Config** `services.<name>.parameters` was removed for generated factories and accessors: parameters are taken from the `create()` method of the implemented interface (95636a7e)
- **Config** `services.<name>.dynamic` was removed (deprecated since 3.1.0): rename the key to `imported`, it is no longer translated automatically (f8a42ff1)
- **Deprecated** `%parameters%` in configuration: use the `@container::getParameters()` call instead; using it triggers `E_USER_DEPRECATED` (c6bf1f99)
- **Deprecated** a locator interface method `create($name)`: declare one no-argument `createFoo()` method per service; using it triggers `E_USER_DEPRECATED` (8140289e)
- **Deprecated** passing an arbitrary object other than `Nette\PhpGenerator\Literal`, `stdClass` or a `UnitEnum` as a configuration argument; using it triggers `E_USER_DEPRECATED` (c257fa97)
- **Signature** `Nette\DI\DynamicParameter` now extends `Nette\PhpGenerator\Literal`; previously `Nette\PhpGenerator\PhpLiteral` (5ded625b)
- **Signature** native PHP 8 types were added throughout, notably to `Nette\DI\CompilerExtension::setConfig()`, `Nette\DI\Extensions\InjectExtension::callInjects()`, `Nette\DI\Container::getDynamicParameter()`, `Nette\DI\Definitions\Statement::__construct()`, `Nette\DI\Definitions\ServiceDefinition::setType()`, `Nette\DI\Definitions\ServiceDefinition::setArguments()` and `Nette\DI\Definitions\ServiceDefinition::addSetup()`; an invalid argument now throws `TypeError` instead of `Nette\InvalidArgumentException`, and subclasses overriding them must match the types (fb3fb6f9)
- **Changed** `Nette\DI\Container` no longer uses `Nette\SmartObject`; reading an undefined property or calling an undefined method no longer throws `Nette\MemberAccessException` (ac1c9586)
- **Changed** `Nette\DI\ContainerBuilder` no longer uses `Nette\SmartObject`; reading an undefined property or calling an undefined method no longer throws `Nette\MemberAccessException` (ac1c9586)
- **Changed** `Nette\DI\CompilerExtension` no longer uses `Nette\SmartObject`; reading an undefined property or calling an undefined method in a custom extension no longer throws `Nette\MemberAccessException` (ac1c9586)
- **Changed** `Nette\DI\Definitions\Definition` no longer uses `Nette\SmartObject`; reading an undefined property or calling an undefined method no longer throws `Nette\MemberAccessException` (ac1c9586)
- **Changed** a non-public or static property marked `@inject` or `#[Inject]` now throws `Nette\InvalidStateException`; previously injection was skipped with `E_USER_WARNING` (2c441b73)
- **Changed** a constructor or method parameter that is nullable or has no declared default value and cannot be autowired now fails compilation with `Nette\DI\ServiceCreationException`; previously it silently received `null`, with `E_USER_DEPRECATED` for non-nullable types; pass the value in the service configuration (dc6605cd)

## 3.1.10

- **Signature** `$args` of `Nette\DI\Container::createService()` was removed (2645ec3e)
- **Changed** `Nette\DI\Extensions\InjectExtension` now detects a missing service for an injected property while compiling the container and throws `Nette\DI\MissingServiceException` naming the property; previously the failure appeared only at runtime (39d559d5)
- **Changed** autowiring of a typed parameter with a default value now falls back to that default when no service matches the type; previously it threw `Nette\DI\ServiceCreationException` (075bc507)

## 3.1.8

- **Deprecated** `Nette\DI\Container::$parameters`: use `Nette\DI\Container::getParameter()` or `Nette\DI\Container::getParameters()`, which also resolve dynamically exported parameters; the old name is silently deprecated (0b89519a)
- **Changed** `Nette\DI\Definitions\ServiceDefinition::getSetup()` now returns setup entities already prefixed with an explicit `@self` reference; previously the prefix was added only during `complete()`, so extensions inspecting the setup saw the raw form (adf47507)
- **Config** `di.export.parameters: false` now makes the generated container omit `getParameters()`, so reading an unexported dynamic parameter throws `Nette\InvalidStateException`; previously the builder parameters were cleared at compile time (0b89519a)
- **Config** `services.<name>.class` combined with `create` is now mapped to `type`; previously the two keys collided (3daeeb0a)

## 3.1.1

- **Removed** `Nette\DI\Config\Adapter::dump()`: the interface no longer declares the method; affects custom adapters and code calling it through the interface (826209f8)
- **Signature** native `object` types were added to `Nette\DI\Container::addService()`, `Nette\DI\Container::callInjects()`, `Nette\DI\Container::createInstance()`, `Nette\DI\Container::createService()`, `Nette\DI\Container::getByName()` and `Nette\DI\Container::getService()`; a non-object argument now throws `TypeError` instead of `Nette\InvalidArgumentException`, and subclasses overriding them must match the types (8257f492)

## 3.1.0

- **Removed** `Nette\DI\Compiler::loadDefinition()` (deprecated since 3.0.0): use `Nette\DI\Compiler::loadDefinitionsFromConfig()` (6dca88ca)
- **Removed** `Nette\DI\Compiler::loadDefinitions()` (deprecated since 3.0.0): use `Nette\DI\Compiler::loadDefinitionsFromConfig()` (6dca88ca)
- **Removed** `Nette\DI\ContainerBuilder::prepareClassList()` (deprecated since 3.0.0): use `Nette\DI\ContainerBuilder::resolve()` (6dca88ca)
- **Removed** `Nette\DI\Definitions\FactoryDefinition::addSetup()` (deprecated since 3.0.0): call `getResultDefinition()->addSetup()` (6dca88ca)
- **Removed** `Nette\DI\Definitions\FactoryDefinition::getEntity()` (deprecated since 3.0.0): call `getResultDefinition()->getEntity()` (6dca88ca)
- **Removed** `Nette\DI\Definitions\FactoryDefinition::getFactory()` (deprecated since 3.0.0): call `getResultDefinition()->getFactory()` (6dca88ca)
- **Removed** `Nette\DI\Definitions\FactoryDefinition::getSetup()` (deprecated since 3.0.0): call `getResultDefinition()->getSetup()` (6dca88ca)
- **Removed** `Nette\DI\Definitions\FactoryDefinition::setArguments()` (deprecated since 3.0.0): call `getResultDefinition()->setArguments()` (6dca88ca)
- **Removed** `Nette\DI\Definitions\FactoryDefinition::setFactory()` (deprecated since 3.0.0): call `getResultDefinition()->setFactory()` (6dca88ca)
- **Removed** `Nette\DI\Definitions\FactoryDefinition::setSetup()` (deprecated since 3.0.0): call `getResultDefinition()->setSetup()` (6dca88ca)
- **Removed** `Nette\DI\Definitions\ServiceDefinition::setClass()` (deprecated since 3.0.0): use `Nette\DI\Definitions\ServiceDefinition::setType()` and pass factory arguments through `setFactory()` (6dca88ca)
- **Removed** `Nette\DI\Definitions\ServiceDefinition::setDynamic()` (deprecated since 3.0.0): register the service with `Nette\DI\ContainerBuilder::addImportedDefinition()` (6dca88ca)
- **Removed** `Nette\DI\Definitions\ServiceDefinition::setImplement()` (deprecated since 3.0.0): register the service with `Nette\DI\ContainerBuilder::addFactoryDefinition()` or `Nette\DI\ContainerBuilder::addAccessorDefinition()` (6dca88ca)
- **Removed** `Nette\DI\Definitions\ServiceDefinition::setInject()` (deprecated since 3.0.0): use `addTag(Nette\DI\Extensions\InjectExtension::TagInject)` (6dca88ca)
- **Removed** `Nette\DI\Definitions\ServiceDefinition::getParameters()` (deprecated since 3.0.0) (6dca88ca)
- **Removed** `Nette\DI\Definitions\ServiceDefinition::setParameters()` (deprecated since 3.0.0) (6dca88ca)
- **Removed** the service tag `run`: a tagged service is no longer initialized when the container starts, without any warning (deprecated since 3.0.0) (6dca88ca)
- **Removed** the `?` operator in a NEON configuration value: using it throws `Nette\DI\InvalidConfigurationException` (deprecated since 3.0.0) (f6b8a034)
- **Renamed** `Nette\DI\Config\DefinitionSchema` → `Nette\DI\Extensions\DefinitionSchema`; the old name was removed (b7ac4628)
- **Renamed** `Nette\DI\Extensions\ConstantsExtension` → `Nette\Bootstrap\Extensions\ConstantsExtension`; using the old name triggers `E_USER_DEPRECATED` (de7bca16)
- **Renamed** `Nette\DI\Extensions\PhpExtension` → `Nette\Bootstrap\Extensions\PhpExtension`; using the old name triggers `E_USER_DEPRECATED` (de7bca16)
- **Renamed** `Nette\DI\ContainerBuilder::THIS_CONTAINER` → `Nette\DI\ContainerBuilder::ThisContainer`; the old name is silently deprecated (16f0ed48)
- **Renamed** `Nette\DI\ContainerBuilder::THIS_SERVICE` → `Nette\DI\ContainerBuilder::ThisService`; the old name is silently deprecated (16f0ed48)
- **Renamed** `Nette\DI\Definitions\Reference::SELF` → `Nette\DI\Definitions\Reference::Self`; the old name is silently deprecated (16f0ed48)
- **Renamed** `Nette\DI\DependencyChecker::VERSION` → `Nette\DI\DependencyChecker::Version`; the old name is silently deprecated (16f0ed48)
- **Renamed** `Nette\DI\Extensions\InjectExtension::TAG_INJECT` → `Nette\DI\Extensions\InjectExtension::TagInject`; the old name is silently deprecated (16f0ed48)
- **Renamed** `$factory` of `Nette\DI\Definitions\ServiceDefinition::setCreator()` → `$creator`; the old name was removed (99c6cd6c)
- **Renamed** the NEON placeholder `...` in entity arguments → `_`; using the old form triggers `E_USER_DEPRECATED` (d624c04d)
- **Renamed** the NEON escape `@@` at the start of a string value → a plain `@`; using the old form triggers `E_USER_DEPRECATED` (a62abfa2)
- **Renamed** `services.<name>.class` → `services.<name>.type`; using the old name triggers `E_USER_DEPRECATED` (046f89cc)
- **Renamed** `services.<name>.dynamic` → `services.<name>.imported`; using the old name triggers `E_USER_DEPRECATED` (046f89cc)
- **Deprecated** `Nette\DI\Config\Loader::save()`: render the configuration with an adapter and write the file yourself; using it triggers `E_USER_DEPRECATED` (ea7451ce)
- **Deprecated** `Nette\DI\Definitions\Definition::getImplement()`: test with `instanceof Nette\DI\Definitions\FactoryDefinition` or `Nette\DI\Definitions\AccessorDefinition`; using it triggers `E_USER_DEPRECATED` (deprecated since 3.0.0) (34a9be3d)
- **Deprecated** `Nette\DI\Definitions\Definition::isDynamic()`: test with `instanceof Nette\DI\Definitions\ImportedDefinition`; using it triggers `E_USER_DEPRECATED` (deprecated since 3.0.0) (34a9be3d)
- **Deprecated** `Nette\DI\Definitions\ImportedDefinition::isDynamic()`: test with `instanceof Nette\DI\Definitions\ImportedDefinition`; using it triggers `E_USER_DEPRECATED` (deprecated since 3.0.0) (34a9be3d)
- **Deprecated** a `@return` annotation on a service factory method: declare a native return type instead; using it triggers `E_USER_DEPRECATED` (e47bf10f)
- **Config** `services.<name>.parameters` on a generated factory is deprecated: declare the parameters directly in the configuration and write `$param` instead of `%param%`; using it triggers `E_USER_DEPRECATED` (694351cd)
- **Changed** `bool()`, `int()`, `float()`, `string()` and `not()` in configuration now require exactly one argument; calling them without one throws `Nette\DI\ServiceCreationException` (e159e9e4)
- **Changed** `Nette\DI\Definitions\FactoryDefinition::setImplement()` now triggers `E_USER_DEPRECATED` when the interface's `create()` has no declared class return type (34f01fe9)
- **Changed** `Nette\DI\Definitions\AccessorDefinition::setImplement()` now triggers `E_USER_DEPRECATED` when the interface's `get()` has no declared non-nullable class return type (34f01fe9)
- **Changed** `Nette\DI\Definitions\LocatorDefinition::setImplement()` now triggers `E_USER_DEPRECATED` when a parameterless `get*()` or `create*()` method has no declared return type (34f01fe9)
- **Changed** a required autowired parameter with no configured value now triggers `E_USER_DEPRECATED`; previously it silently received `null`; pass the value in the service configuration (40bbcac5)
- **Changed** a string naming a class constant used in configuration arguments is now evaluated when the container is generated, as a PHP literal; previously it was evaluated while the configuration was processed (4e01cda1)
- **Config** `decorator.<type>.tags` values are now passed through unchanged; previously they went through `Nette\DI\Helpers::filterArguments()`, so `@service` became a reference and a string naming a class constant was evaluated (48162fcc)

## 3.0.17

- **Changed** an argument named after a variadic parameter in `services.<name>.arguments` is now spread into the individual variadic arguments and a non-array value throws `Nette\DI\ServiceCreationException`; previously the array was passed through as a single named value; also in 3.1.8 (dc0e9f7e)

## 3.0.16

- **Changed** values for a variadic parameter given in `services.<name>.arguments` as a named array under the parameter's own name are no longer unpacked, and a positional argument supplied after a named or omitted one now throws `Nette\DI\ServiceCreationException`; also in 3.1.2 (8e6357f8)

## 3.0.15

- **Changed** a nullable return type such as `?Foo` declared on a factory or accessor interface method is no longer accepted; declare a non-nullable class type (dad71aae)

## 3.0.13

- **Config** a single named argument of `services.<name>.implement` is now recognized as a service reference of a locator; previously it was taken as an argument of the factory statement (1c2351b6)

## 3.0.12

- **Renamed** `Nette\DI\Definitions\ServiceDefinition::setFactory()` → `Nette\DI\Definitions\ServiceDefinition::setCreator()`; the old name still works as an alias (11c236b9)
- **Renamed** `Nette\DI\Definitions\ServiceDefinition::getFactory()` → `Nette\DI\Definitions\ServiceDefinition::getCreator()`; the old name still works as an alias (11c236b9)
- **Changed** keyed entries in `services.<name>.arguments` that override a parent definition are now merged with `array_replace`, so the override wins; previously they were merged with `+`, which kept the parent's value on a key collision (71ba33b8)
- **Changed** an extra positional entry in `services.<name>.arguments` beyond the declared parameters, following one that fell back to its default, now throws `Nette\DI\ServiceCreationException`; previously it was accepted and appended (eeb3d032)

## 3.0.11

- **Changed** a non-public or static property marked `@inject` or `#[Inject]` is no longer injected; it now triggers `E_USER_WARNING`, so make the property public and non-static (1e7b63f1)
- **Changed** a readonly property marked `@inject` or `#[Inject]` now throws `Nette\InvalidStateException`; previously injection was attempted (1e7b63f1)
- **Changed** the `_` placeholder is recognized only inside entity-call syntax and removed while NEON is parsed; a bare `_` elsewhere in `services.<name>.arguments` is now passed through literally instead of being skipped (7d7a7100)
- **Changed** the generated container no longer checks that a factory's return value matches the declared service type; a mismatch now surfaces only through the factory method's own return type, previously through `Nette\UnexpectedValueException` (0d522a96)
- **Changed** a named and a positional argument given for the same parameter no longer throws; the named value wins and the positional one is discarded (8d8450a6)

## 3.0.10

- **Renamed** `$type` of `Nette\DI\Definitions\AccessorDefinition::setImplement()` → `$interface`; the old name was removed (b8bf1f22)
- **Renamed** `$type` of `Nette\DI\Definitions\FactoryDefinition::setImplement()` → `$interface`; the old name was removed (b8bf1f22)
- **Renamed** `$type` of `Nette\DI\Definitions\LocatorDefinition::setImplement()` → `$interface`; the old name was removed (b8bf1f22)
- **Renamed** `services.<name>.factory` → `services.<name>.create`; the old name still works as an alias (f36567e2)
- **Changed** a factory definition now throws `Nette\DI\ServiceCreationException` when the resolved factory type is not compatible with the return type of the interface's `create()`; previously the mismatch went undetected (cc7c4e78)
- **Changed** a named and a positional argument given for the same parameter now throws `Nette\DI\ServiceCreationException`; previously the named value silently won (4f0cb0b3)
- **Changed** an empty service definition in configuration now throws `Nette\DI\InvalidConfigurationException`; previously it was treated as an empty service definition (3c615cb3)
- **Changed** a property marked `@inject` or `#[Inject]` with a missing or unresolvable type now throws `Nette\DI\ServiceCreationException`; previously `Nette\InvalidStateException`, and the compile-time check for a matching service was dropped, so that failure appears only at runtime (d27967f0)

## 3.0.7

- **Changed** `Nette\DI\Compiler` now passes configuration warnings produced by `Nette\Schema` on through `E_USER_DEPRECATED`; previously they were discarded (33b188dd)
- **Config** `decorator.<class>` naming a class that does not exist now throws `Nette\DI\InvalidConfigurationException`; previously it was silently ignored (74ae22f0)

## 3.0.6

- **Changed** a property marked `@inject` whose `@var` annotation holds a union type now throws `Nette\InvalidStateException`; previously the type was parsed (ad1d29c6)
- **Config** `di.debugger`: when the key is not set, the Tracy bar is enabled according to whether a `Tracy\Bar` service is registered; previously according to whether the Tracy classes are installed (c8743ea3)
- **Config** `php.<directive>`: the current value of a directive is now compared strictly as a string, so `false` is compared against `''` and a previously accepted configuration may throw `Nette\NotSupportedException` (78f52123)

## 3.0.5

- **Changed** `not()` in configuration now throws `Nette\DI\ServiceCreationException` when called with more than one argument (f1061b94)

## 3.0.4

- **Changed** `Nette\DI\Extensions\InjectExtension` now calls `inject*()` methods in their natural declaration order; previously they were ordered by inheritance and alphabetically (9e6be87c)
- **Changed** `Nette\DI\Extensions\SearchExtension` now throws `Nette\InvalidStateException` when a found class cannot be autoloaded; previously an unhandled `ReflectionException` escaped (55c591f7)
- **Config** services found through `search` are now also subject to `decorator` rules, because the search extension is always processed first (de2404ed)
- **Config** `decorator` now resolves definitions before matching them by type, so it also matches services whose type was not yet known (5d946fdc)

## 3.0.1

- **Changed** `Nette\DI\Container::addService()` no longer triggers `E_USER_NOTICE` for a service that is not declared in the container; previously it reported that the service should be defined as `imported` (a8cbbcab)
- **Config** `extensions.<name>`: the class must extend `Nette\DI\CompilerExtension`, otherwise `Nette\DI\InvalidConfigurationException` is thrown; previously the type was not checked (a49bf895)

## 3.0.0

- **Removed** `Nette\DI\Config\Adapters\IniAdapter`: convert the configuration to NEON (2e709efe)
- **Removed** support for `.ini` configuration files in `Nette\DI\Config\Loader`: convert them to NEON (2e709efe)
- **Removed** configuration section inheritance, written `key < parent` in NEON and `[section < parent]` in INI: merge the configuration explicitly (ad8b5b84)
- **Removed** `Nette\DI\Config\Helpers::isInheriting()` (ad8b5b84)
- **Removed** `Nette\DI\Config\Helpers::OVERWRITE`: use `Nette\DI\Config\Helpers::PREVENT_MERGING` (8bc1fc0b)
- **Removed** `Nette\DI\Config\Helpers::isOverwriting()`: use `Nette\DI\Config\Helpers::takeParent()`, which now reports only whether merging is prevented (8bc1fc0b)
- **Removed** `Nette\DI\Container::expand()` (2759712a)
- **Removed** `Nette\DI\ContainerBuilder::expand()` (2759712a)
- **Removed** `Nette\DI\ContainerBuilder::getClassName()`: the container class name belongs to `Nette\DI\Compiler` (2759712a)
- **Removed** `Nette\DI\Statement::setEntity()`: construct a new statement instead of mutating the existing one (2759712a)
- **Removed** `Nette\DI\Compiler::parseService()` (deprecated since 2.4): use `Nette\DI\Compiler::loadDefinitionsFromConfig()` (2759712a)
- **Removed** `Nette\DI\Compiler::parseServices()`: use `Nette\DI\Compiler::loadDefinitionsFromConfig()` (2759712a)
- **Removed** `Nette\DI\ServiceDefinition::getInject()`: use `getTag(Nette\DI\Extensions\InjectExtension::TAG_INJECT)` (2759712a)
- **Removed** `Nette\DI\ServiceDefinition::getImplementType()` (deprecated since 2.4): the mode follows from the chosen definition class (2759712a)
- **Removed** `Nette\DI\ServiceDefinition::setImplementType()` (deprecated since 2.4): the mode follows from the chosen definition class (2759712a)
- **Removed** `Nette\DI\ServiceDefinition::getImplementMode()`: the mode follows from whether the definition is a `Nette\DI\Definitions\FactoryDefinition` or a `Nette\DI\Definitions\AccessorDefinition` (7e0def06)
- **Removed** `Nette\DI\ServiceDefinition::setImplementMode()`: register the service with `Nette\DI\ContainerBuilder::addFactoryDefinition()` or `Nette\DI\ContainerBuilder::addAccessorDefinition()` (7e0def06)
- **Removed** `Nette\DI\ServiceDefinition::$parameters`: read them through `Nette\DI\Definitions\ServiceDefinition::getParameters()`, which is itself deprecated (b364d583)
- **Config** `services.<name>.create` was removed: use `factory` (2759712a)
- **Config** `services.<name>.run` was removed: use `tags: {run: true}` (2759712a)
- **Config** service definition inheritance written `services: {name < parent: ...}` was removed (ad8b5b84)
- **Config** `di.accessors` was removed (2e2f5cd1)
- **Renamed** `Nette\DI\Config\IAdapter` → `Nette\DI\Config\Adapter`; the old name still works as an alias (4e254271)
- **Renamed** `Nette\DI\ServiceDefinition` → `Nette\DI\Definitions\ServiceDefinition`; the old name still works as an alias (a0d36119)
- **Renamed** `Nette\DI\Statement` → `Nette\DI\Definitions\Statement`; the old name still works as an alias (a0d36119)
- **Renamed** `Nette\DI\Compiler::filterArguments()` → `Nette\DI\Helpers::filterArguments()`; the old name was removed (2759712a)
- **Renamed** `Nette\DI\ContainerBuilder::prepareClassList()` → `Nette\DI\ContainerBuilder::resolve()`; using the old name triggers `E_USER_DEPRECATED` (31fb741b)
- **Renamed** `Nette\DI\Config\Helpers::EXTENDS_KEY` → `Nette\DI\Config\Helpers::PREVENT_MERGING`; the old name was removed, and the new constant carries only a boolean flag instead of the parent's value (8bc1fc0b)
- **Renamed** `Nette\DI\Definitions\Definition::getClass()` → `Nette\DI\Definitions\Definition::getType()`; the old name is silently deprecated (1705a5db)
- **Renamed** `Nette\DI\Definitions\ServiceDefinition::setClass()` → `Nette\DI\Definitions\ServiceDefinition::setType()`; the old name is silently deprecated (1705a5db)
- **Renamed** `$class` of `Nette\DI\Container::getByType()` → `$type`; the old name was removed (58602234)
- **Renamed** `$class` of `Nette\DI\Container::findByType()` → `$type`; the old name was removed (58602234)
- **Renamed** `$class` of `Nette\DI\ContainerBuilder::getByType()` → `$type`; the old name was removed (58602234)
- **Renamed** `$class` of `Nette\DI\ContainerBuilder::findByType()` → `$type`; the old name was removed (58602234)
- **Renamed** `$class` of `Nette\DI\ContainerBuilder::getDefinitionByType()` → `$type`; the old name was removed (58602234)
- **Renamed** `$classes` of `Nette\DI\ContainerBuilder::addExcludedClasses()` → `$types`; the old name was removed (58602234)
- **Renamed** `services.<name>.class` → `services.<name>.type`; the old name still works as an alias (4241f919)
- **Renamed** `services.<name>.dynamic` → `services.<name>.imported`; the old name still works as an alias (40484ba7)
- **Replaced** `Nette\DI\Compiler::loadDefinitions()` with `Nette\DI\Compiler::loadDefinitionsFromConfig()`: the new method is not static, takes no namespace and expects a list of normalized definitions; calling the old one throws `Nette\DeprecatedException` (e4744caa)
- **Replaced** `Nette\DI\Compiler::loadDefinition()` with `Nette\DI\Compiler::loadDefinitionsFromConfig()`: calling the old one throws `Nette\DeprecatedException` (e4744caa)
- **Replaced** `Nette\DI\Compiler::compile($config, $className)` with `Nette\DI\Compiler::addConfig()` and `Nette\DI\Compiler::setClassName()` called before `compile()`, which takes no arguments (2759712a)
- **Replaced** `Nette\DI\CompilerExtension::getConfig($arg)` with `Nette\DI\CompilerExtension::validateConfig()`: `getConfig()` no longer accepts an argument (2759712a)
- **Replaced** `Nette\DI\Container::$meta` with `Nette\DI\Container::$types`, `Nette\DI\Container::$aliases`, `Nette\DI\Container::$tags` and `Nette\DI\Container::$wiring`; affects subclasses and hand-written containers (fbebca4e)
- **Replaced** `Nette\DI\Container::SERVICES` with `Nette\DI\Container::$types` (fbebca4e)
- **Replaced** `Nette\DI\Container::ALIASES` with `Nette\DI\Container::$aliases` (fbebca4e)
- **Replaced** `Nette\DI\Container::TAGS` with `Nette\DI\Container::$tags` (fbebca4e)
- **Replaced** `Nette\DI\Container::TYPES` with `Nette\DI\Container::$wiring` (fbebca4e)
- **Replaced** `Nette\DI\Container::__get()` with `Nette\DI\Container::getService()`: reading a service as a property no longer works (2759712a)
- **Replaced** `Nette\DI\Container::__isset()` with `Nette\DI\Container::hasService()` (2759712a)
- **Replaced** `Nette\DI\Container::__set()` with `Nette\DI\Container::addService()` (2759712a)
- **Replaced** `Nette\DI\Container::__unset()` with `Nette\DI\Container::removeService()` (2759712a)
- **Replaced** `Nette\DI\ContainerBuilder::setClassName()` with `Nette\DI\Compiler::setClassName()` (2759712a)
- **Replaced** `Nette\DI\ContainerBuilder::formatStatement()` with `Nette\DI\PhpGenerator::formatStatement()` (2759712a)
- **Replaced** `Nette\DI\ContainerBuilder::generateClasses()` with `Nette\DI\PhpGenerator::generate()` (2759712a)
- **Replaced** `Nette\DI\ContainerBuilder::completeStatement()` with `Nette\DI\Resolver::completeStatement()` (fc15e9f7)
- **Replaced** `Nette\DI\Helpers::autowireArguments()` with `Nette\DI\Resolver::autowireArguments()` (c91dc5ee, f42afff8)
- **Replaced** `Nette\DI\Config\Helpers` with `Nette\Schema\Helpers`: merging of configuration is delegated there (f3ce39c8)
- **Replaced** a `Nette\DI\ContainerBuilder` instance used as a statement entity or argument with the string `@container` (2759712a)
- **Replaced** hand-written `createService*()` methods on a container subclass with services declared in the configuration: a service that is not listed in the container's types is no longer found (51eb031a)
- **Replaced** `Nette\DI\Definitions\ServiceDefinition::setImplement()` with `Nette\DI\ContainerBuilder::addFactoryDefinition()` or `Nette\DI\ContainerBuilder::addAccessorDefinition()`: calling it throws `Nette\DeprecatedException` (7e0def06)
- **Replaced** `Nette\DI\Definitions\ServiceDefinition::setDynamic()` with `Nette\DI\ContainerBuilder::addImportedDefinition()`: calling it throws `Nette\DeprecatedException` (7e0def06)
- **Replaced** `Nette\DI\Definitions\ServiceDefinition::setParameters()` with arguments declared in the service configuration: calling it throws `Nette\DeprecatedException` (b364d583)
- **Deprecated** `Nette\DI\Compiler::getConfig()`: configurations are now merged through the schema; the old name is silently deprecated (53129a9d)
- **Deprecated** `Nette\DI\CompilerExtension::validateConfig()`: describe the configuration in `Nette\DI\CompilerExtension::getConfigSchema()`; the old name is silently deprecated (f3ce39c8)
- **Deprecated** `Nette\DI\Config\Helpers`: use `Nette\Schema\Helpers`; the old name is silently deprecated (f3ce39c8)
- **Deprecated** `Nette\DI\Definitions\Definition::getImplement()`: test with `instanceof Nette\DI\Definitions\FactoryDefinition` or `Nette\DI\Definitions\AccessorDefinition`; the old name is silently deprecated (fdad4c0c)
- **Deprecated** `Nette\DI\Definitions\Definition::isDynamic()`: test with `instanceof Nette\DI\Definitions\ImportedDefinition`; the old name is silently deprecated (40484ba7)
- **Deprecated** `Nette\DI\Definitions\ImportedDefinition::isDynamic()`: test with `instanceof Nette\DI\Definitions\ImportedDefinition`; the old name is silently deprecated (40484ba7)
- **Deprecated** `Nette\DI\Definitions\ServiceDefinition::getParameters()`: using it triggers `E_USER_DEPRECATED` (b364d583)
- **Deprecated** `Nette\DI\Definitions\ServiceDefinition::setInject()`: use `addTag(Nette\DI\Extensions\InjectExtension::TAG_INJECT)`; using it triggers `E_USER_DEPRECATED` (11067ee0)
- **Deprecated** `$args`, the second parameter of `Nette\DI\Definitions\ServiceDefinition::setClass()`: set factory arguments with `setFactory()`; using it triggers `E_USER_DEPRECATED` (af825975)
- **Deprecated** `Nette\DI\Definitions\FactoryDefinition::addSetup()`: call `getResultDefinition()->addSetup()`; using it triggers `E_USER_DEPRECATED` (a1d8f4d3)
- **Deprecated** `Nette\DI\Definitions\FactoryDefinition::getEntity()`: call `getResultDefinition()->getEntity()`; using it triggers `E_USER_DEPRECATED` (a1d8f4d3)
- **Deprecated** `Nette\DI\Definitions\FactoryDefinition::getFactory()`: call `getResultDefinition()->getFactory()`; using it triggers `E_USER_DEPRECATED` (a1d8f4d3)
- **Deprecated** `Nette\DI\Definitions\FactoryDefinition::getSetup()`: call `getResultDefinition()->getSetup()`; using it triggers `E_USER_DEPRECATED` (a1d8f4d3)
- **Deprecated** `Nette\DI\Definitions\FactoryDefinition::setArguments()`: call `getResultDefinition()->setArguments()`; using it triggers `E_USER_DEPRECATED` (a1d8f4d3)
- **Deprecated** `Nette\DI\Definitions\FactoryDefinition::setFactory()`: call `getResultDefinition()->setFactory()`; using it triggers `E_USER_DEPRECATED` (a1d8f4d3)
- **Deprecated** `Nette\DI\Definitions\FactoryDefinition::setSetup()`: call `getResultDefinition()->setSetup()`; using it triggers `E_USER_DEPRECATED` (a1d8f4d3)
- **Deprecated** the `?` operator in a NEON configuration value; using it triggers `E_USER_DEPRECATED` (acf2ce41)
- **Deprecated** the service tag `run`: services are no longer meant to be initialized this way; using it triggers `E_USER_DEPRECATED` (594123e7)
- **Signature** the classes `Nette\DI\Config\Adapters\NeonAdapter`, `Nette\DI\Config\Adapters\PhpAdapter`, `Nette\DI\Config\Helpers`, `Nette\DI\Extensions\ConstantsExtension`, `Nette\DI\Extensions\DIExtension`, `Nette\DI\Extensions\DecoratorExtension`, `Nette\DI\Extensions\ExtensionsExtension`, `Nette\DI\Extensions\InjectExtension`, `Nette\DI\Extensions\PhpExtension`, `Nette\DI\ServiceDefinition` and `Nette\DI\Statement` are now `final` and can no longer be extended (661c19e4)
- **Signature** native scalar and return types were added throughout, notably to `Nette\DI\Container::addService()`, `Nette\DI\Container::getService()`, `Nette\DI\Container::getByType()`, `Nette\DI\CompilerExtension::getConfig()`, `Nette\DI\CompilerExtension::getContainerBuilder()`, `Nette\DI\CompilerExtension::prefix()`, `Nette\DI\ContainerLoader::generate()`, `Nette\DI\Config\Adapter::load()` and `Nette\DI\Config\Adapter::dump()`; subclasses and custom adapters overriding them must declare matching types (3c8edece)
- **Signature** `Nette\DI\ContainerBuilder::addDefinition()` now accepts `?string $name` and returns `Nette\DI\Definitions\Definition` instead of `Nette\DI\Definitions\ServiceDefinition`; chaining methods specific to a service definition needs an `instanceof` check, and passing `null` now creates an anonymous service instead of throwing (f80194bc, c2eccd04)
- **Signature** `Nette\DI\ContainerBuilder::getDefinition()` now returns `Nette\DI\Definitions\Definition` instead of `Nette\DI\Definitions\ServiceDefinition`; chaining methods specific to a service definition needs an `instanceof` check (f80194bc)
- **Signature** `Nette\DI\ContainerBuilder::getDefinitionByType()` now returns `Nette\DI\Definitions\Definition` instead of `Nette\DI\Definitions\ServiceDefinition`; chaining methods specific to a service definition needs an `instanceof` check (f80194bc)
- **Signature** `Nette\DI\Config\Loader::load()`: the `$section` parameter was removed and replaced with `?bool $merge` (ad8b5b84)
- **Signature** `Nette\DI\ContainerLoader::load()` takes the generator callback first and the key second; the legacy order was removed (2759712a)
- **Signature** `$phpFiles` of `Nette\DI\DependencyChecker::isExpired()` is now taken by reference and filled with modification times (c3b2e689)
- **Signature** `Nette\DI\Definitions\ServiceDefinition::getFactory()` no longer returns `null`; the factory statement is always initialized, so null checks on the result are unnecessary (54f6f794)
- **Signature** `Nette\DI\PhpGenerator::generate()` now returns a single `Nette\PhpGenerator\ClassType` instead of an array of classes (c577868e)
- **Signature** `Nette\DI\CompilerExtension::setConfig()` accepts an object besides an array and `Nette\DI\CompilerExtension::getConfig()` no longer declares an `array` return type; affects subclasses overriding them (0b634ca5)
- **Changed** the generated container class now declares `strict_types=1`, so arguments defined in the configuration are coerced more strictly (0be938be)
- **Changed** `Nette\DI\Compiler::addExtension()` now throws `Nette\InvalidArgumentException` for a name that differs from an existing one only in letter case (9de80d01)
- **Changed** `Nette\DI\ContainerBuilder::addDefinition()` now throws `Nette\InvalidStateException` for a service name that differs from an existing one only in letter case (9de80d01)
- **Changed** `Nette\DI\ContainerBuilder::addDefinition()` now throws immediately when a service named `container` is added, because that definition is created in the constructor; previously it was silently discarded later (41fac90b)
- **Changed** `Nette\DI\ContainerBuilder::findByType()` now returns every service assignable to the type; previously only services eligible for autowiring (b38a92a4)
- **Changed** `Nette\DI\ContainerBuilder::getByType()` and `Nette\DI\ContainerBuilder::findByType()` now throw `Nette\DI\NotAllowedDuringResolvingException` when called while the container is being resolved (e107e726)
- **Changed** `Nette\DI\Container::getByType()` and `Nette\DI\Container::findByType()` now normalize the given name through reflection, so a name in the wrong letter case or one created by `class_alias()` resolves correctly; previously it did not match (2feee106)
- **Changed** a class name written in the wrong letter case in a service definition is now silently normalized; previously it threw `Nette\DI\ServiceCreationException` (2feee106)
- **Changed** `Nette\DI\Container::$types` in the generated container now lists only imported services; read the type of any other service with `Nette\DI\Container::getServiceType()` (af03cf92)
- **Changed** `Nette\DI\Container::getMethodName()` no longer inserts an extra `__` separator for a service name that already starts with an uppercase letter; affects hand-written `createService*()` methods (51eb031a)
- **Changed** `Nette\DI\Container::getMethodName()` now throws `Nette\InvalidArgumentException` for an empty name; previously it returned `createService` (802ddc22)
- **Changed** `Nette\DI\Container::addService()` now triggers `E_USER_NOTICE` for a service that is not declared in the container; declare the service as `imported` in the configuration (0d453f0a)
- **Changed** every service definition must now have a resolvable type, even when `autowired: false`; add an explicit `type` when it cannot be inferred, otherwise compilation throws (3d5d5402)
- **Changed** `Nette\DI\Definitions\ServiceDefinition::setType()` and `setImplement()` now verify immediately that the class or interface exists and throw `Nette\InvalidArgumentException`; previously the check was deferred to compilation (160db78a)
- **Changed** `Nette\DI\Definitions\Statement::__construct()` now normalizes the entity, so `getEntity()` may return a `Nette\DI\Definitions\Reference` or an array where a string was passed, and an invalid array entity throws `Nette\InvalidArgumentException` (df561de6)
- **Changed** errors while processing a service configuration now throw `Nette\DI\InvalidConfigurationException`; code catching `Nette\DI\ServiceCreationException` no longer catches them (659b2a38)
- **Changed** a missing service required by a property marked `@inject` now throws `Nette\DI\MissingServiceException`; previously `Nette\InvalidStateException` (16a8dfd8)
- **Changed** a service accessor defined with `implement` that declares a `setup` section now throws `Nette\DI\ServiceCreationException` (98eb0e61)
- **Changed** `Nette\DI\Helpers::expand()` now expands parameters in array keys as well as in values, so a configuration with a literal `%` in a key may fail or produce a different key (85175340)
- **Changed** `Nette\DI\PhpGenerator::generate()` no longer calls `Nette\DI\ContainerBuilder::complete()`; call it yourself when generating without `Nette\DI\Compiler` (2c2c018b)
- **Changed** `Nette\DI\Compiler::addConfig()`: definitions are now loaded separately for each added configuration; previously all configurations were merged first, which changes how same-named and auto-named services across files interact (4d5a17fe)
- **Changed** `Nette\DI\Config\Helpers::merge()` now preserves integer keys that do not form a list; previously any integer key was dropped and its value appended (a8928716)
- **Changed** `Nette\DI\Extensions\DecoratorExtension` no longer decorates a service accessor by the type it returns; only a service whose `implement` interface itself matches the decorated type is decorated (b36d0418)
- **Changed** `Nette\DI\Extensions\InjectExtension` now applies the service tag `nette.inject`; previously `inject`, so code adding the tag by literal string silently stops working (0be978f8)
- **Changed** a service defined under a numeric key in NEON is now fully anonymous; previously its name was derived from its factory (86106eb2)
- **Config** a service name must now match `^\w+(\.\w+)*$` and must not be purely numeric, otherwise `Nette\InvalidArgumentException` is thrown (d7940c4e)
- **Config** a file that includes itself through `includes` now throws `Nette\InvalidStateException`; previously the loader recursed (d5b64c15)
- **Config** `services.<name>.type` no longer accepts a factory expression and no longer clears a `factory` inherited from a parent definition; put the expression under `factory` (cfccb4de)
- **Config** `di.debugger` is now enabled by default only when Tracy is installed; previously it was always enabled (d17f164d)
- **Config** a dynamic parameter whose value does not match its declared type now throws a validation exception when the container is created; previously the value passed unchecked (e3199c54)
- **Config** a non-scalar value of a directive in the `php` section now fails schema validation with `Nette\DI\InvalidConfigurationException`; previously `Nette\InvalidStateException` (4e93ff27)
