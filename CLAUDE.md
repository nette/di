# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Nette DI** is a compiled Dependency Injection Container for PHP - a core component of the Nette Framework. This is a library/framework component, not an application.

**Key characteristics:**
- Compiled container generates optimized PHP code for maximum performance
- Full autowiring support with type-based dependency resolution
- NEON configuration format for human-friendly service definitions
- Supports PHP 8.1 - 8.5
- ~5,900 lines of production code

## Essential Commands

### Running Tests

Tests use Nette Tester (not PHPUnit) with `.phpt` file format:

```bash
# Run all tests
vendor/bin/tester tests -s -C

# Run specific directory
vendor/bin/tester tests/DI/ -s -C

# Run specific test file
vendor/bin/tester tests/DI/Compiler.configurator.phpt -s -C
```

**Flags explained:**
- `-s` - show output from tests
- `-C` - use system-wide php.ini

### Static Analysis

```bash
# Run PHPStan (level 5)
composer run phpstan
```

### Code Quality

```bash
# Quick validation
composer run tester
composer run phpstan
```

## Test Infrastructure

### Test File Structure

All tests use `.phpt` format with embedded test cases:

```php
<?php
declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

test('description of what is tested', function () {
	$container = createContainer(new Compiler, 'services: ...neon...');
	Assert::type(ServiceClass::class, $container->getByType(ServiceClass::class));
});

testException('description', function () {
	// code that should throw
}, ExpectedException::class, 'Expected message');
```

### Test Helpers (from bootstrap.php)

**`createContainer($source, $config, $params = [])`**
- Compiles and instantiates container from config
- `$source` can be `Compiler` or `ContainerBuilder`
- `$config` is NEON string or file path
- Returns compiled container instance
- Generated code saved to `tests/tmp/{pid}/code.php` for debugging

**`getTempDir()`**
- Returns per-process temporary directory: `tests/tmp/{pid}/`
- Automatically cleaned via garbage collection

**`Notes::add($message)` / `Notes::fetch()`**
- Test notification system for debugging
- Add messages during execution, fetch for assertions

### Common Test Patterns

**Testing service registration:**
```php
$container = createContainer(new Compiler, '
	services:
		- MyService
');
```

**Testing with fixtures:**
```php
$loader = new Loader;
$config = $loader->load(__DIR__ . '/files/config.neon');
$container = createContainer(new Compiler, $config);
```

**Testing generated code:**
```php
$builder = new ContainerBuilder;
$builder->addDefinition('foo')->setType(MyClass::class);
$code = (new PhpGenerator($builder))->generate('Container1');
// inspect $code
```

## Architecture Overview

### Compilation Flow

The container compilation happens in distinct phases:

1. **Load** - Configuration files loaded and merged (`Config\Loader`)
2. **Extensions** - Compiler extensions process configuration and register services (`CompilerExtension`)
3. **Resolve** - Dependencies resolved, types validated (`Resolver`)
4. **Generate** - PHP code generated for container class (`PhpGenerator`)
5. **Runtime** - Compiled container instantiated and used (`Container`)

### Core Components

**`Container.php`** (11KB)
- Runtime container holding service instances
- Provides services via `getService()`, `getByType()`, `getByName()`
- Manages autowiring metadata, tags, aliases
- Lazy loading and circular dependency detection

**`Compiler.php`** (8.6KB)
- Orchestrates compilation process
- Manages compiler extensions
- Loads and processes configuration
- Generates container code

**`ContainerBuilder.php`** (9.8KB)
- Builds service definition graph during compilation
- Central registry for all service definitions
- Handles autowiring setup
- Validates service configurations

**`Resolver.php`** (21KB - largest file)
- Core dependency resolution logic
- Resolves `Reference`, `Statement`, and type references
- Detects circular dependencies
- Handles complex autowiring scenarios

**`PhpGenerator.php`** (5.6KB)
- Generates optimized PHP code for container
- Uses `nette/php-generator` for code emission
- Creates type-safe service factory methods
- Produces highly optimized, readable code

### Service Definitions (`src/DI/Definitions/`)

Multiple definition types for different service patterns:

- **`ServiceDefinition`** - Standard service with constructor/setup
- **`FactoryDefinition`** - Auto-generated factory from interface
- **`AccessorDefinition`** - Service accessor (getter)
- **`LocatorDefinition`** - Dynamic service locator
- **`ImportedDefinition`** - External service reference
- **`Reference`** - Reference to another service (`@serviceName`)
- **`Statement`** - Callable/function call (`trim(...)`)

### Configuration System (`src/DI/Config/`)

**`NeonAdapter.php`** - Primary configuration format
- Processes NEON syntax into service definitions
- Handles special syntax:
  - `@serviceName` - service references
  - `%paramName%` - parameter expansion
  - `trim(...)` - first-class callable statements
  - `!` suffix - prevent merging
- Uses visitor pattern with `NodeTraverser`

**`Loader.php`** - Configuration file loading
- Merges multiple config files
- Environment-specific overrides
- Parameter inheritance

### Extension System (`src/DI/Extensions/`)

Built-in extensions providing core functionality:

- **`ServicesExtension`** - Registers services from `services:` section
- **`ParametersExtension`** - Handles container parameters
- **`SearchExtension`** - Auto-registration by file patterns
- **`InjectExtension`** - Property/method injection (`#[Inject]`)
- **`DecoratorExtension`** - Service decoration patterns
- **`DIExtension`** - DI-specific configuration
- **`ExtensionsExtension`** - Extension management

Create custom extensions by extending `CompilerExtension`.

### Tracy Integration (`src/Bridges/DITracy/`)

Debug panel showing:
- All registered services with types
- Service tags and wiring info
- Container parameters
- Compilation time
- Service instantiation status

## Key Design Patterns

1. **Compiled Container Pattern** - Container pre-generated as PHP code, not interpreted at runtime
2. **Builder Pattern** - `ContainerBuilder` constructs service graph before code generation
3. **Visitor Pattern** - NEON adapter uses traverser with visitors to process configuration tree
4. **Extension Point Pattern** - `CompilerExtension` allows pluggable compilation customization
5. **Lazy Loading** - Services instantiated on-demand, not upfront
6. **Code Generation** - Runtime container is optimized PHP code with zero interpretation overhead

## NEON Configuration Syntax

The primary configuration format uses special syntax understood by `NeonAdapter`:

**Service references:**
```neon
services:
	logger: FileLogger
	mailer:
		factory: Mailer
		setup:
			- setLogger(@logger)  # Reference by name
			- setConnection(@Nette\Database\Connection)  # Reference by type
```

**First-class callables (since 3.2.0):**
```neon
services:
	- MyService(trim(...))        # Callable passed as argument
	- Factory::create(...)         # Factory method callable
	- UserService(@user::logout(...))  # Equivalent to [@user, 'logout']
```

**Parameters:**
```neon
parameters:
	logFile: /var/log/app.log
	mailer:
		host: smtp.example.com
		user: admin

services:
	- FileLogger(%logFile%)           # Parameter expansion
	- Mailer(%mailer.host%, %mailer.user%)  # Nested parameter access
```

**Expression language - create objects and call functions:**
```neon
services:
	- DateTime()                       # Create object
	- Collator::create(%locale%)       # Call static method
	database: DatabaseFactory::create()
	router: @routerFactory::create()   # Call method on service
```

**Method chaining (use `::` instead of `->`):**
```neon
parameters:
	currentDate: DateTime()::format('Y-m-d')
	# PHP: (new DateTime())->format('Y-m-d')

	host: @http.request::getUrl()::getHost()
	# PHP: $this->getService('http.request')->getUrl()->getHost()
```

**Special functions:**
```neon
services:
	- Foo(
		id: int(::getenv('ProjectId'))        # Lossless type casting
		productionMode: not(%debugMode%)       # Boolean negation
		bars: typed(Bar)                       # Array of all Bar services
		loggers: tagged(logger)                # Array of services with 'logger' tag
	)
```

**Constants:**
```neon
services:
	- DirectoryIterator(%tempDir%, FilesystemIterator::SKIP_DOTS)
	phpVersion: ::constant(PHP_VERSION)
```

**Prevent merging with `!` suffix:**
```neon
services:
	database!: CustomConnection  # Won't be merged with parent config
items!:                          # Replace array instead of merging
	- newItem
```

## Autowiring Behavior

Autowiring automatically passes services to constructors and methods based on type hints. Understanding its nuances is critical when working with this codebase.

### Basic Autowiring Rules

- **Exactly one service** of each type must exist in the container
- Multiple services of same type cause autowiring to fail with exception
- Services can be excluded from autowiring using `autowired: false`

### Disabling Autowiring

```neon
services:
	mainDb: PDO(%dsn%, %user%, %password%)

	tempDb:
		create: PDO('sqlite::memory:')
		autowired: false    # Excluded from autowiring

	articles: ArticleRepository  # Gets mainDb injected
```

**Important:** In Nette, `autowired: false` means "don't pass this service to others" (different from Symfony where it means "don't autowire constructor args").

### Autowiring Preference

When multiple services of same type exist, mark one as preferred:

```neon
services:
	mainDb:
		create: PDO(%dsn%, %user%, %password%)
		autowired: PDO    # Becomes preferred for PDO type

	tempDb:
		create: PDO('sqlite::memory:')

	articles: ArticleRepository  # Gets mainDb
```

### Narrowing Autowiring

Limit which types a service can be autowired for:

```neon
services:
	parent: ParentClass
	child:
		create: ChildClass
		autowired: ChildClass    # Only autowired for ChildClass type, not ParentClass
		# Can also use 'self' as alias for current class

	parentDep: ParentDependent   # Gets parent service
	childDep: ChildDependent     # Gets child service
```

Multiple types can be specified:

```neon
autowired: [BarClass, FooInterface]
```

**How narrowing works:** Service is only autowired when the required type matches or is a subtype of the narrowed type.

### Collection of Services

Autowiring can pass arrays of services:

```php
class ShipManager
{
	/**
	 * @param Shipper[] $shippers
	 */
	public function __construct(array $shippers)
	{}
}
```

The container automatically passes all `Shipper` services (excluding those with `autowired: false`).

Alternative using `typed()` function:

```neon
services:
	- ShipManager(typed(Shipper))
```

### Scalar Arguments

Autowiring only works for objects and arrays of objects. Scalar values (strings, numbers, booleans) must be specified in configuration or wrapped in a settings object.


## Service Definition Patterns

### Service Creation Methods

**Simple class instantiation:**
```neon
services:
	database: PDO('sqlite::memory:')
```

**Multi-line with additional configuration:**
```neon
services:
	database:
		create: PDO('sqlite::memory:')    # or 'factory:' (both work)
		setup: ...
		tags: ...
```

**Static method factories:**
```neon
services:
	database: DatabaseFactory::create()
	router: @routerFactory::create()    # Call method on another service
```

**With explicit type (when return type not declared):**
```neon
services:
	database:
		create: DatabaseFactory::create()
		type: PDO
```

### Arguments

**Named arguments (preferred for clarity):**
```neon
services:
	database: PDO(
		username: root
		password: secret
		dsn: 'mysql:host=127.0.0.1;dbname=test'
	)
```

**Omit arguments to use defaults or autowiring:**
```neon
services:
	foo: Foo(_, %appDir%)    # First arg autowired, second is parameter
```

### Setup Section

Call methods after service creation:

```neon
services:
	database:
		create: PDO(%dsn%, %user%, %password%)
		setup:
			- setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION)
			- $value = 123                    # Set property
			- '$onClick[]' = [@bar, clickHandler]  # Add to array
			- My\Helpers::initializeFoo(@self)     # Pass service to static method
			- @anotherService::setFoo(@self)       # Call method on other service
```

### Lazy Services (PHP 8.4+)

Enable globally or per-service:

```neon
di:
	lazy: true    # Global setting

services:
	foo:
		create: Foo
		lazy: false    # Override for specific service
```

Lazy services return proxy objects; actual instantiation happens on first method/property access. Only works for user-defined classes.

### Tags

Organize and query services by tags:

```neon
services:
	foo:
		create: Foo
		tags:
			- cached
			logger: monolog.logger.event    # Tag with value
```

Retrieve tagged services:

```php
$names = $container->findByTag('logger');
// ['foo' => 'monolog.logger.event', ...]
```

Or in configuration:

```neon
services:
	- LoggersDependent(tagged(logger))
```

### Service Modifications

Modify services registered by extensions:

```neon
services:
	application.application:
		create: MyApplication
		alteration: true    # Indicates we're modifying existing service
		setup:
			- '$onStartup[]' = [@resource, init]
```

Remove original configuration:

```neon
services:
	application.application:
		alteration: true
		reset:
			- arguments
			- setup
			- tags
```

Remove service entirely:

```neon
services:
	cache.journal: false
```


## Configuration Sections

### Decorator

Apply setup to all services of a specific type:

```neon
decorator:
	App\Presentation\BasePresenter:
		setup:
			- setProjectId(10)
			- $absoluteUrls = true

	InjectableInterface:
		tags: [mytag: 1]
		inject: true
```

Useful for:
- Calling methods on all presenters
- Setting tags on interfaces
- Enabling inject mode for specific types

### Search (Auto-registration)

Automatically register services by file/class patterns:

```neon
search:
	-	in: %appDir%/Forms
		files:
			- *Factory.php
		classes:
			- *Factory

	-	in: %appDir%/Model
		extends:
			- App\*Form
		implements:
			- App\*FormInterface
		exclude:
			files: ...
			classes: ...
		tags: [autoregistered]
```

**Filtering options:**
- `files:` - Filter by filename pattern
- `classes:` - Filter by class name pattern
- `extends:` - Select classes extending specified classes
- `implements:` - Select classes implementing interfaces
- `exclude:` - Exclusion rules (same keys as above)
- `tags:` - Tags to assign to all registered services

### DI Section

Technical container configuration:

```neon
di:
	debugger: true              # Show DIC in Tracy Bar
	excluded: [...]             # Parameter types never autowired
	lazy: false                 # Enable lazy services globally (PHP 8.4+)
	parentClass: ...            # Base class for DI container

	export:
		parameters: false       # Don't export parameters to metadata
		tags:                   # Export only specific tags
			- event.subscriber
		types:                  # Export only specific types for autowiring
			- Nette\Database\Connection
```

**Metadata optimization:** Reduce generated container size by limiting exported metadata to only what's actually used.

### Including Files

```neon
includes:
	- parameters.php    # Can include PHP files returning arrays
	- services.neon
	- presenters.neon
```

**Merging behavior:**
- Later files override earlier ones
- Arrays are merged (unless `!` suffix used)
- File containing `includes` has higher priority than included files


## Extension Development Lifecycle

Extensions customize the compilation process by implementing up to 4 methods called sequentially:

### 1. getConfigSchema()

Define and validate extension configuration:

```php
class BlogExtension extends Nette\DI\CompilerExtension
{
	public function getConfigSchema(): Nette\Schema\Schema
	{
		return Expect::structure([
			'postsPerPage' => Expect::int(),
			'allowComments' => Expect::bool()->default(true),
		]);
	}
}
```

Access config via `$this->config` (stdClass object).

### 2. loadConfiguration()

Register services to container:

```php
public function loadConfiguration()
{
	$builder = $this->getContainerBuilder();
	$builder->addDefinition($this->prefix('articles'))
		->setFactory(App\Model\HomepageArticles::class, ['@connection'])
		->addSetup('setLogger', ['@logger']);
}
```

**Important:** Use `$this->prefix('name')` to avoid service name conflicts.

**Loading from NEON:**

```php
public function loadConfiguration()
{
	$this->compiler->loadDefinitionsFromConfig(
		$this->loadFromFile(__DIR__ . '/blog.neon')['services'],
	);
}
```

In NEON file, use `@extension` to reference current extension's services.

### 3. beforeCompile()

Modify existing services or establish relationships:

```php
public function beforeCompile()
{
	$builder = $this->getContainerBuilder();

	foreach ($builder->findByTag('logaware') as $serviceName => $tagValue) {
		$builder->getDefinition($serviceName)->addSetup('setLogger');
	}
}
```

Called after all `loadConfiguration()` methods complete. Service graph is fully defined.

### 4. afterCompile()

Modify generated container class:

```php
public function afterCompile(Nette\PhpGenerator\ClassType $class)
{
	$method = $class->getMethod('__construct');
	// Modify generated PHP code
}
```

Container class already generated as `ClassType` object. Can modify before writing to cache.

### $initialization

Add code to run after container instantiation:

```php
public function loadConfiguration()
{
	// Auto-start session
	if ($this->config->session->autoStart) {
		$this->initialization->addBody('$this->getService("session")->start()');
	}

	// Instantiate services tagged with 'run'
	foreach ($this->getContainerBuilder()->findByTag('run') as $name => $foo) {
		$this->initialization->addBody('$this->getService(?);', [$name]);
	}
}
```


## Generated Factories and Accessors

Nette DI can generate factory and accessor implementations from interfaces.

### Generated Factories

**Define interface:**

```php
interface ArticleFactory
{
	function create(): Article;
}
```

**Register in config:**

```neon
services:
	- ArticleFactory
```

Nette generates the implementation. Dependencies autowired into `Article` constructor.

**Parameterized factories:**

```php
interface ArticleFactory
{
	function create(int $authorId): Article;
}

class Article
{
	public function __construct(
		private Nette\Database\Connection $db,
		private int $authorId,    // Matched by name from factory method
	) {}
}
```

**Advanced configuration:**

```neon
services:
	articleFactory:
		implement: ArticleFactory
		arguments:
			authorId: 123    # Fixed value passed to constructor
		setup:
			- setAuthorId($authorId)    # Or via setter
```

### Accessors

Provide lazy-loading for dependencies:

```php
interface PDOAccessor
{
	function get(): PDO;
}
```

```neon
services:
	- PDOAccessor
	- PDO(%dsn%, %user%, %password%)
```

Accessor returns same instance on repeated calls. Database connection only created on first `get()` call.

If multiple services of same type exist, specify which one: `- PDOAccessor(@db1)`

### Multifactory/Accessor

Combine multiple factories and accessors in one interface:

```php
interface MultiFactory
{
	function createArticle(): Article;
	function getDb(): PDO;
}
```

**Definition with list (3.2.0+):**

```neon
services:
	- MultiFactory(
		article: Article
		db: PDO(%dsn%, %user%, %password%)
	)
```

**Or with references:**

```neon
services:
	article: Article
	- PDO(%dsn%, %user%, %password%)
	- MultiFactory(
		article: @article
		db: @\PDO
	)
```


## Development Workflow

### Adding New Features

When adding features to the DI container:

1. **Determine scope** - Does it need new definition type, compiler extension, or core change?
2. **Update definitions** - Add to `ContainerBuilder` if new service type
3. **Implement resolution** - Update `Resolver` if special dependency handling needed
4. **Generate code** - Modify `PhpGenerator` to emit correct PHP code
5. **Add tests** - Create `.phpt` test files demonstrating usage
6. **Update docs** - Changes to configuration syntax need documentation

### Debugging Tips

**Inspect generated container:**
- Generated code is cached in temp directory
- Use `ContainerLoader` with `autoRebuild: true` during development
- Check `tests/tmp/{pid}/code.php` during test runs

**Use Tracy panel:**
- Shows all registered services and their state
- Reveals autowiring metadata
- Displays compilation time

**Test helpers:**
- `Notes::add()` for debug messages in tests
- Examine `tests/DI/expected/` for expected code output
- Use `Tester\FileMock::create()` for inline NEON configs

### Common Gotchas

- **Strict types required** - All files must have `declare(strict_types=1)`
- **NEON syntax sensitivity** - Indentation matters, references need `@` prefix
- **Circular dependencies** - Resolver detects but requires careful definition ordering
- **Generated code cache** - Use `autoRebuild: true` to avoid stale container during development
- **Test isolation** - Each test gets unique container class name via counter

## Code Style

Follows Nette Coding Standard (based on PSR-12):

- Strict types declaration in all files
- PascalCase for classes, camelCase for methods/properties
- Type hints for all parameters, properties, return values
- Two empty lines between methods (per Nette convention)
- Exceptions grouped in `exceptions.php` files
- Natural language exception messages (e.g., "The file does not exist.")

## Important Files

**Entry points for understanding:**
- `readme.md` - Excellent overview with working examples
- `src/DI/Container.php` - Runtime behavior
- `src/DI/Compiler.php` - Compilation orchestration
- `src/DI/Resolver.php` - Dependency resolution logic
- `tests/DI/*.phpt` - Real usage patterns

**Configuration examples:**
- `tests/DI/files/*.neon` - Test fixtures showing NEON syntax
- `tests/DI/expected/*.php` - Expected generated container code
