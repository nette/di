# Config loading, merging & parameters

## Cache & recompilation

`ContainerLoader::load()` derives the class name from a hash of the cache key
(`Container_<hash>`). If the class isn't loaded it `@include`s the compiled file when
not expired (no compilation), else takes an **exclusive `flock` lock** on a sibling
`.lock` file (against concurrent compilation), re-checks expiry under the lock,
generates the code, and writes both the `.php` and its `.meta` **atomically** (`.tmp`
+ `rename`, with `opcache_invalidate` before *and* after the rename; on Windows the
rename retries briefly against transient file locks). `isExpired()` returns **`false`
always when `autoRebuild === false`** (production never recompiles); in debug it
defers to `DependencyChecker`, whose meta records the mtime of every config/PHP file
**and a structural hash of every touched class** (parents, interfaces, traits, `use`
statements, public members with signatures *and docComments*, `#[Inject]` on
properties). The rebuild rule is asymmetric: a **config-file mtime change always
rebuilds** (content-independent), but a PHP-file mtime change rebuilds **only when
the structural hash changed too** — otherwise the cached container is kept and just
the mtimes in `.meta` are refreshed. Dependency files a custom extension reads must
be registered via `$builder->addDependency($file)` or the cache won't know about
them.

## Loading & merging

`Config\Loader` picks an adapter by extension: `.php` is trivial (`return require`);
`.neon` runs `NeonAdapter` (below). With the default `merge: true`, `includes` are
resolved first (a later include wins) and the main file is merged over them. But
`Compiler::loadConfig()` loads with `merge: false`: the loader returns a **flat list**
of sources with the main file **appended last**, stored as `$configs[section][] =
$data` — **the cross-source merge per section happens later**, in phase A via Schema
(`processSchema` → `Schema\Processor::processMultiple`, where the **last** dataset
wins — which is what makes the main file override its includes).
Merge semantics (`Schema\Helpers::merge`): the **left/new operand wins**; numeric keys
append (lists concatenate), string keys merge recursively, `null` does not overwrite an
array, a scalar does; a `PREVENT_MERGING` marker returns the left operand only.

## NEON adapter

`NeonAdapter` parses NEON to an AST, runs a chain of visitors, then `process()`. The
facts that matter downstream:

- **`@` escaping.** A **quoted** string starting with `@` is doubled to `@@` (in
  quotes `@` means literal text, not a reference); an **unquoted** `@foo` passes
  through and becomes a reference later. So `@foo` is a reference, `'@foo'` is the
  literal text `@foo`.
- **Entities become `Statement`s.** `Foo(a, b)` → `new Statement('Foo', ['a', 'b'])`;
  a chain `Foo()::bar()::baz()` nests them.
- **`!` suffix = prevent-merge.** A key ending `!` means "replace, don't merge" and
  injects the `PREVENT_MERGING` marker.

Crucially, the adapter **does not** turn `@service` argument strings into `Reference`
objects — they stay strings until an extension calls `Helpers::filterArguments`
(see resolution.md).

## `%param%` expansion — once, at the start of phase A

Because `ParametersExtension` runs **first**, `%param%` is expanded across the entire
config before any other extension (including `ServicesExtension`) sees its section. In
`loadConfiguration` it:

1. replaces each **dynamic** parameter with a `DynamicParameter('$this->getParameter(...)')`
   object;
2. expands `%param%` **inside the parameters themselves** (recursively — a parameter
   may reference another);
3. expands `%param%` **in the whole rest of the config** (its `compilerConfig` is a
   **reference** to `Compiler::$configs`).

`Helpers::expand()`: a `%%` is a literal `%`; a placeholder that is the **entire
string** (`%foo%` alone) returns the value **as-is** (so `%mailer%` can return a whole
array), otherwise it concatenates into a string (a non-scalar embedded in a larger
string throws; a *dynamic* value embedded in one becomes a `::implode` `Statement`);
dotted notation `%foo.bar%` reaches into nested arrays; a cyclic reference or a
missing parameter throws.

## Static vs dynamic parameters

A value that varies by environment (an env var, `baseUrl` from the request) must stay
**dynamic**. Names are declared via `Compiler::setDynamicParameterNames()` (Bootstrap
passes the dynamic-parameter names plus `baseUrl`, which is always dynamic).
`ParametersExtension::afterCompile` splits them: **static** parameters are baked into
`getStaticParameters()`; **dynamic** ones (carrying a `DynamicParameter` or `Statement`)
generate a `getDynamicParameter($key)` computed at runtime; and dynamic-value
**validation** is emitted into `initialize()` as `Validators::assert()`. At runtime
`Container::getParameter()` lazily computes a dynamic parameter on first access
(deadlock-guarded). `Helpers::escape()` (`%`→`%%`, leading `@`→`@@`) is the inverse,
used by Bootstrap on programmatically-injected values so they aren't misread as
placeholders.
