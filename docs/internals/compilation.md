# Compilation phases & introspection safety

`Compiler::compile()` is three steps, and the whole model hinges on what each
guarantees:

```php
$this->processExtensions();     // PHASE A: schemas + loadConfiguration
$this->processBeforeCompile();  // PHASE B: resolve + beforeCompile + complete
return $this->generateCode();   // PHASE C: generate + afterCompile
```

- **Phase A** fills the definition graph. Service **types are not yet reliably known.**
- **Phase B** resolves all types (`resolve`), lets extensions edit the graph
  (`beforeCompile`), then **autowires arguments** (`complete`).
- **Phase C** generates PHP and lets extensions touch the code.

## Extension order is deliberate — and load-bearing

`processExtensions()` runs `getConfigSchema` → `setConfig` → `loadConfiguration` on
each extension, but in a carefully controlled order:

1. **`ParametersExtension` + `ExtensionsExtension` first.** Parameters must expand
   `%param%` across the *whole* config before any other extension sees its section;
   Extensions registers further extensions from `extensions:`, so it must exist before
   the rest run.
2. **`SearchExtension` just before `DecoratorExtension`** — Search registers
   discovered classes (in its `beforeCompile`) so Decorator can then apply setup/tags
   to them.
3. Everyone else: all `setConfig`, then all `loadConfiguration`.
4. **`InjectExtension` moved to the very end** — its `beforeCompile` must see the
   setups added by everyone else.
5. **`ServicesExtension` last** — the user `services:` section always gets the last
   word and can override anything an extension set.

Two errors are guarded at the end: an extension registered later than it should have
been, and an orphan config section with no matching extension (with a "did you mean"
suggestion).

At the end of phase A the graph is **complete in count** (every extension and the
user registered what they meant to), but service types from factory return values
are unresolved, arguments are not autowired, and `@service` references are still
partly strings — which is exactly why type introspection here is unreliable.

## When `ContainerBuilder` introspection is safe

This is the question every extension author hits. Two flags and one exception decide
it. `ContainerBuilder` tracks `needsResolve` (set `true` after **any** definition
change) and `resolving` (`true` while `resolve()` runs). The type-lookup methods
(`getByType`, `getDefinitionByType`, `findByType`, `findAutowired`) all route through
a private guard:

- if `resolving` → **throw `NotAllowedDuringResolvingException`** (you are inside
  `resolve()`);
- else if `needsResolve` → **lazily run `resolve()`** first.

**`findByTag()` does not go through this guard** — tags don't depend on types, so
tag lookup works in **every** phase.

Two related traps: the guard is absolute — during `resolve()` even
`getByType($type, throw: false)` throws. And the queries differ: `getByType`/
`findAutowired` answer from the autowiring index (honouring `autowired: false` and
excluded classes), while `findByType` scans all definitions by declared type and
**ignores autowiring settings** — they can return different sets.

Phase by phase:

- **`loadConfiguration()` (phase A) — type introspection is unreliable.** The graph is
  incomplete (later extensions and the user `services:` are not registered yet). A
  `getByType()` "works" but answers from a partial graph *and* forces a premature
  resolve. Rule: **only register definitions here; do not look up by type.**
  `findByTag()` is fine.
- **`beforeCompile()` (phase B) — the right place.** `processBeforeCompile` runs
  `builder->resolve()` **first** (types resolved, autowiring index built), *then* the
  `beforeCompile()` loop, and `builder->complete()` **only after** all of them. So in
  `beforeCompile` every definition exists, types are resolved, and
  `getByType`/`findByType`/`findByTag` are **reliable** — but **arguments are not yet
  autowired**. Editing a definition here sets `needsResolve` (via a notifier hook
  installed by `addDefinition`), and the next `getByType()` transparently
  re-resolves, so you can freely interleave edits and queries. This holds only
  until `complete()`, which detaches all notifiers — the graph is frozen from
  there on.
- **`afterCompile()` (phase C)** operates on the generated `ClassType`, not the
  builder.

| I want to… | phase |
|---|---|
| register a service | `loadConfiguration()` |
| look up by **tag** and edit definitions | `loadConfiguration()` or `beforeCompile()` |
| look up by **type** | **`beforeCompile()`** |
| touch generated code | `afterCompile()` |
| run code after container start | `$this->initialization` (see code-generation.md) |
