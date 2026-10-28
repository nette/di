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

**Two strategies.** Without auto-rebuild (`ContainerLoader::loadOnce`) the file
declares `Container_<key>` itself and is included at most once per process: a loaded
class is returned without touching the disk. With auto-rebuild
(`ContainerLoader::loadCurrent`) PHP's inability to redeclare a class would make a
worker, dev server or MCP introspector serve the first container forever, so **each
build declares a fresh class `Container_<key>_<random>`** and the file ends with
`return '<that name>';`. The loader keeps a per-process registry *compiled file →
content hash → loaded class* and includes the file only when its content has not been
loaded yet (the hash also detects a rebuild done by another process). Consequences:
`load()` returns the declared name, not `getClassName($key)`; `getClassName()` stays
a valid class name because the first loaded container gets it as a `class_alias`.
Files declaring the base name (built without auto-rebuild, by older versions or by
custom generators that ignore the requested name) are handled as well. **Never share
one cache key between the two modes in one process**: a file rebuilt without
auto-rebuild redeclares the base name that the auto-rebuild side already holds as an
alias, which is a fatal error (`Bootstrap\Configurator` keys on `debugMode`, so it
cannot happen there).

## Loading & merging

`Config\Loader` picks an adapter by extension: `.php` is trivial (`return require`);
`.neon` runs `NeonAdapter` (below). With the default `merge: true`, `includes` are
resolved first (a later include wins) and the main file is merged over them. But
`Compiler::loadConfig()` loads with `merge: false`: the loader returns a **flat list**
of sources with the main file **appended last**, stored as `$configs[section][] =
$data` — **the cross-source merge per section happens later**, in phase A via Schema
(`processSchema` → `Schema\Processor::processMultiple`, where the **last** dataset
wins — which is what makes the main file override its includes).
Merge semantics are those of the schema of each section. DI's own merge
(`Config\Helpers::merge`, also used by `Loader` for includes): the **left/new operand
wins**; numeric keys append (lists concatenate), string keys merge recursively, `null`
does not overwrite an array, a scalar does; a `PREVENT_MERGING` marker returns the left
operand only. `parameters` declare exactly this merge (`mergeWith()`), because
nette/schema 2.0 does not merge an undescribed array deeply by itself.

**`!` markers are resolved by DI, not by Schema.** Before `processMultiple`,
`Compiler::resolveMergingMarkers()` strips every marker from the layers of a section and
drops its path from the earlier layers, so nette/schema never sees one, whether 1.4 or
2.0 is installed. A path through a numeric key is not dropped: items under it are
appended, so an earlier layer holds a different item there (another anonymous
service). In the `services` section the marker also becomes `reset` (`all` for a
definition, or `arguments`/`setup`/`tags`), which reaches definitions created by
extensions too.

A value that is not an array cannot carry the marker, so the adapter wraps it in
`Config\Replacement`. The wrapper survives `%param%` expansion, which matters: other
sections are expanded **before** their layers merge, so `list!: %param%` holding an
array would otherwise merge with earlier layers. A service definition that is not an
array gets its `reset` from an extra leading layer, as each layer is normalized by the
schema on its own.

## NEON adapter

`NeonAdapter` reads NEON through `NeonEvaluator`, a subclass of the `Evaluator` of
nette/neon 4, which builds the whole configuration from the tree in one pass. The keys
`@a` and `@@a`, or `a` and `a!`, in one array are a duplicated key. The facts that
matter downstream:

- **`@` escaping.** A **quoted** string starting with `@` is doubled to `@@` (in
  quotes `@` means literal text, not a reference); an **unquoted** `@foo` passes
  through and becomes a reference later. So `@foo` is a reference, `'@foo'` is the
  literal text `@foo`.
- **Entities become `Statement`s.** `Foo(a, b)` → `new Statement('Foo', ['a', 'b'])`;
  a chain `Foo()::bar()::baz()` nests them.
- **`!` suffix = prevent-merge.** A key ending `!` means "replace, don't merge" and
  injects the `PREVENT_MERGING` marker into an array, or wraps any other value in
  `Config\Replacement`.

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
