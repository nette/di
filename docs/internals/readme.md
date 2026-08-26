# DI internals

How Nette DI compiles a container, for agents editing it. The compiler is the
richest emergent model in the framework: an A/B/C phase system across
`Compiler`/`ContainerBuilder`/`Resolver`/extensions, where **what is safe to do
depends entirely on which phase you are in**. Split by seam:

- **[compilation.md](compilation.md)** — the phase system, extension ordering, and
  the rule for when `ContainerBuilder` introspection is safe.
- **[config-loading.md](config-loading.md)** — config loading/merging, the NEON
  adapter, `%param%` expansion, and static vs dynamic parameters.
- **[resolution.md](resolution.md)** — type resolution, the autowiring index, the
  `@service`→`Reference`→name translation, and argument autowiring.
- **[code-generation.md](code-generation.md)** — code generation per definition
  type, `afterCompile`, and what the generated `Container` does at runtime.

## Who owns what

All in `src/DI`:

| class | role |
|---|---|
| `Compiler` | phase orchestration, config collection, extension registry |
| `ContainerBuilder` | the definition graph + the `needsResolve`/`resolving` guards |
| `Resolver` | per-definition type & argument resolution (also reused at runtime) |
| `Autowiring` | the type → service-name index |
| `Definitions/*` | the recipe kinds (Service/Factory/Accessor/Locator/Imported), `Statement`, `Reference` |
| `PhpGenerator` | emits the container class from completed definitions |
| `Container` | runtime base class: instances, `wiring`/`tags` metadata |
| `ContainerLoader` + `DependencyChecker` | disk cache & expiration |
| `Config/*` | file loading & adapters, NEON → `Statement` |

## Two worlds

The container is **not built per request.** It is compiled **once** into an
optimized PHP class (cached on disk); later requests just `include` it. Everything
below runs only at (re)compilation.

| | at compile time | at runtime |
|---|---|---|
| what exists | **definitions** (recipes) in `ContainerBuilder` | **instances** in `Container` |
| `%param%`, `@service` | text markers being translated | already baked into code |

## The compile timeline

```
Loader.load()        configs from files; NEON → Statement/array; '@' in quotes → @@ (sections merge in phase A)
Compiler::compile()
├─ PHASE A processExtensions()   register definitions
│   ParametersExtension FIRST → %param% expanded across the whole config
│   ServicesExtension LAST      → services: → Definition objects
│   [graph complete in count; TYPES and ARGUMENTS not yet resolved]
├─ PHASE B processBeforeCompile()
│   builder.resolve()   → resolveType of all; Autowiring.rebuild()  [types & index ready]
│   beforeCompile()     → HERE getByType/findByType/findByTag are reliable
│   builder.complete()  → argument autowiring; @service::CONST/::prop; type refs → names
└─ PHASE C generateCode()        Statement → PHP; createServiceXxx() methods; afterCompile()

RUNTIME (each request): new Container($dynamicParams); initialize(); lazy getService()
```

## The five most common mistakes

- **"I'll look services up by type in `loadConfiguration()`."** No — the graph is
  incomplete (user `services:` runs after you) and `getByType()` triggers a premature
  resolve of a partial graph. Move it to `beforeCompile()`. `findByTag()` is fine.
- **"A `getenv()` parameter differs per environment."** Only if it is **dynamic**;
  otherwise it is baked at compile time.
- **"`@Type` is immediately a service name."** No — it is a *type* reference, resolved
  to a name by autowiring in the **complete** phase.
- **"`initialization->addBody()` runs at compile time."** No — it is PHP emitted into
  `initialize()`, which runs on **every** request. Keep it small.
- **"I can call `getByType()` during `resolve()`."** No — it throws
  `NotAllowedDuringResolvingException`.
