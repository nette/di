# Type resolution, autowiring & references

Phase B has two sub-steps with a strict ordering: **`resolve()` figures out types and
builds the autowiring index; `complete()` then autowires arguments.** They are
separate because argument autowiring needs a finished index.

## `resolve()` — types and the index

`ContainerBuilder::resolve()` sets `resolving = true`, runs `resolveType` on every
definition (guarding circular references via `SplObjectStorage`; a type that stays
unknown throws `Type of service is unknown`), then `Autowiring::rebuild()`, then clears
the flags.

`resolveType` derives a service's type from its `type` or its entity (a factory
method's return type, the class, or a reference); an alias (entity is a `Reference`)
is automatically `autowired = false` (only when autowiring was left at the default
`true`). **It resolves only the type, not arguments** —
the arguments need the index that this pass is still building.

`Autowiring::rebuild()` maps **every type of each definition** (class + parents +
interfaces) to a service name across two levels — **highPriority** (preferred, normal)
and **lowPriority** (suppressed, e.g. when another service is marked preferred via
`autowired: Type`). `autowired: false` keeps the type out of the index entirely;
`autowired: [A, B]` narrows which types the service is visible for.

## `@service` → `Reference` → name is a three-step translation

The translation is spread across phases by how complex the string is:

| form | becomes a `Reference`/expression at | resolves to a concrete service at |
|---|---|---|
| entity of a `Statement` (`@foo` as factory) | parse (`Statement` ctor) | complete |
| an argument `@foo` / `@Type` | phase A (`Helpers::filterArguments`) | complete (`normalizeReference`) |
| `@foo::CONST`, `@foo::prop` | phase B (`Resolver::convertReferences`) | complete |
| a type reference `@Type` | phase A/B | complete (`getByType`) |

- `filterArguments` (phase A) only catches a **bare** `@name`/`@Type` (regex
  `#^@[\w\\]+$#D`) → `new Reference(...)`. It does **not** catch `@foo::bar`.
- `convertReferences` (phase B, complete) is the smart pass: `@service` → `Reference`;
  `@service::CONST` → a `Class::CONST` **literal**; `@service::property` → a `Statement`
  reading the property; `@@x` → the literal `@x`.
- A `Reference` may hold a **name** (`isName`), a **type** (`isType` — contains `\`,
  so a namespaceless `@Foo` is a *name* reference, not a type), or **self**
  (`isSelf`). `Resolver::normalizeReference` (in complete) verifies a name reference
  (rewriting a reference to the service itself to `Reference::Self`), and **resolves
  a type reference to a name via `getByType()`** — but if
  that lookup can't run yet (mid-resolve → `NotAllowedDuringResolvingException`), it
  **leaves the reference typed** and resolves it later.

That last point is the bridge: **autowiring lookup is not performed during `resolve()`**
(the index may be incomplete), it is deferred to complete.

## `complete()` — argument autowiring

`ContainerBuilder::complete()` re-`resolve()`s (types current), then **detaches the
change-notifier from every definition** (the graph is frozen from here), then runs
`completeDefinition` on each. `Resolver::completeStatement` is the heart: it converts
references, and per entity kind (class / function / static or instance method /
`not` / a cast / a first-class callable) reflects the target and **fills missing
arguments by autowiring**, recursing into nested statements and `typed()`/`tagged()`
collections.

Argument autowiring respects positional and named arguments and variadics
(`#[SensitiveParameter]` only wraps an explicitly passed scalar in a
sensitive-marked literal in the generated code), and resolves a missing object /
`Type[]` argument through a getter — `getByType($type)` for a single service, or all
autowired services of the type for an array. **This is where type-based autowiring
actually happens** — in complete, with a finished index — which is why type
references were left unresolved during resolve. A special case: a service's own
setup may autowire *itself* (`currentServiceAllowed`), and `getByType` returns
`Reference::Self`; in the creator/constructor the same situation is forbidden and
throws `MissingServiceException`.

## Definition types

Each service kind is a `Definition` with the triad `resolveType` / `complete` /
`generateMethod`:

- **`ServiceDefinition`** — a normal service. A pure alias (`Reference`, no args/setup)
  completes to `$this->getService('name')`; otherwise creator and setups are autowired;
  `generateMethod` emits `return new Foo(...)` (or `$service = …; $service->setup();
  return $service;`), wrapping in `newLazyGhost(...)` when `lazy` and eligible.
- **`FactoryDefinition`** — a factory generated from an interface. Type is the
  interface; it **matches `create()`'s parameters to the constructor's by name**;
  `generateMethod` emits an **anonymous class** implementing the interface.
- **`AccessorDefinition`** — an anonymous class whose `get()` returns an existing
  service.
- **`LocatorDefinition`** — a bundle of factories/accessors (fixed-name
  `getXxx()`/`createXxx()` methods or dynamic `get($name)`/`create($name)`).
- **`ImportedDefinition`** — resolve/complete are empty; `generateMethod` emits a body
  that **always throws** ("must be added using addService()") — the instance is
  supplied at runtime via `Container::addService()`.
