# Code generation & runtime

## Phase C: `generateCode()`

`Compiler::generateCode()` builds a `PhpGenerator`, generates the class, then loops
extensions to let each `afterCompile($class)` edit the code and contribute boot code to
`initialize()`.

`PhpGenerator::generate()` creates a class extending `Container` (with
`parent::__construct($params)`), fills the `aliases`/`tags`/`wiring` properties from
`builder->exportMeta()`, and for **every** definition emits a `createServiceXxx()`
method by delegating to that definition's `generateMethod` (see resolution.md) — the
method name is `createService` + the ucfirst'd service name with dots turned into
`__` (`mail.mailer` → `createServiceMail__mailer`).
`formatStatement` translates a `Statement` into PHP (`new Foo(...)`, method calls,
property get/set, static calls, functions), and `convertArguments` turns a `Reference`
into `$this->getService(...)`, self into `$service`, and the container into `$this`.

## `afterCompile` and `initialize()`

Extensions use `afterCompile` to mutate the finished `ClassType` — e.g.
`ParametersExtension` emits `getStaticParameters()`/`getDynamicParameter()`, and
`DIExtension` sets the parent class, restricts exported metadata, and injects the Tracy
panel in debug.

**The critical distinction:** `$this->initialization` (a per-extension closure,
emitted into the container's `initialize()` method and invoked there when non-empty)
runs **after the container is constructed, on every request** — not at compile time.
So it must hold only small runtime actions:
starting a session, sending HTTP headers, `define()`, `ini_set()`, validating dynamic
parameters. Putting heavy work in `initialization->addBody()` runs it every request.

## Runtime: the generated `Container`

At runtime none of the above runs; the generated `Container` just reads precomputed
metadata:

- **`getService($name)`** lazily creates the instance via `createServiceXxx()` and
  **caches** it (aliases redirect); `createService` is deadlock-guarded.
- **`getByType($type)`** reads **only the high-priority bucket** of the precomputed
  `wiring` index — exactly one service there returns it, more than one throws
  "Multiple services" (even if lower-priority candidates exist), zero gives a
  specific error (does the type exist / is it not autowired / missing from the
  export).
- **`findByType`/`findByTag`** read the `wiring`/`tags` metadata (`findByType` merges
  all buckets, `findAutowired` only high + low); `getParameter` lazily computes
  dynamic parameters. `wiring` carries three buckets `[high, low, no]`: the high/low
  split comes from `Autowiring::rebuild`, the third — type-matching but
  **non-autowired** services — is added by `ContainerBuilder::exportMeta`.

Two runtime facts invisible from the compile-time side:

- **Service existence is the method map.** The constructor snapshots
  `get_class_methods($this)`; `hasService`/`getService` are driven by that map plus
  the `instances`/`factories` added via `addService()`. At runtime "the service
  exists" means "a `createServiceXxx()` method exists" — definitions are gone.
- **Runtime autowiring reuses the compile-time engine.** `createInstance()`,
  `callMethod()` and inject processing call the static
  `Resolver::autowireArguments()` — the same argument matching that fills arguments
  in the `complete` phase. Changing it in `Resolver` changes both compile-time and
  runtime behaviour.

`di: export:` restricts what metadata is emitted (parameters/tags/types), which shrinks
the generated container when the full autowiring index isn't needed at runtime.
