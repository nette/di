# To My Agents!

It is my fervent wish that this file guide every AI coding agent working with code in this repository.

## Documentation

Any distilled, agent-facing documentation for this package - how it works
internally and the rationale behind key design decisions - lives in `docs/`.
Consult it before non-trivial changes; it is the source of truth from which the
public manual is distilled.

The compiler is the richest emergent model in the framework: an A/B/C phase system
where **what is safe to do depends entirely on which phase you are in**. Read
`docs/internals/` before changing compilation, resolution, or code generation - it
will save you from the classic phase-ordering bugs.

## Project Overview

**Nette DI** is a *compiled* Dependency Injection container: the service graph is
resolved once and emitted as an optimized PHP class (cached on disk), so runtime
requests just `include` it. Full autowiring, NEON configuration, and a
`CompilerExtension` plug-in system. Library component, not an application.

- **PHP Version**: 8.1 - 8.5
- **Package**: `nette/di`

## Essential Commands

```bash
# Run all tests
vendor/bin/tester tests -s -C        # or: composer tester

# Run one directory / file
vendor/bin/tester tests/DI/ -s -C
vendor/bin/tester tests/DI/Compiler.configurator.phpt -s -C

# Static analysis (PHPStan level 5)
composer phpstan
```

`-C` uses the system-wide php.ini.

## Test Infrastructure

- Tests are Nette Tester `.phpt` files; `tests/bootstrap.php` provides
  `createContainer($source, $config, $params = [])` (compiles a `Compiler`/
  `ContainerBuilder` + NEON into a live container), `getTempDir()`, and
  `Notes::add()`/`fetch()` for in-test tracing.
- **The compiled code is written to `tests/tmp/{pid}/code.php`** - read it when a
  generation test fails. Expected output fixtures live in `tests/DI/expected/`.

## Conventions

- Every file starts with `declare(strict_types=1);`; everything typed; two blank
  lines between methods; Nette Coding Standard.
- Exceptions are grouped in `exceptions.php`; messages are natural language
  ("The file does not exist.").

## Working in this repo

The container has **two worlds**: at compile time only *definitions* (recipes)
exist in `ContainerBuilder`; at runtime only *instances* exist in `Container`.
`%param%` and `@service` are text markers translated during compilation, already
baked into code at runtime. The compile timeline is three phases (see
`docs/internals/compilation.md`), and most bugs come from ignoring it:

- **Don't look services up by type in `loadConfiguration()`.** The graph is
  incomplete (user `services:` register last) and `getByType()` forces a premature
  resolve. Move type introspection to `beforeCompile()`. `findByTag()` is fine any time.
- **`getByType()` during `resolve()` throws `NotAllowedDuringResolvingException`.**
- **`@Type` is a *type* reference, not a service name** - autowiring translates it
  to a name in the `complete` phase, so it stays unresolved during `resolve()`.
- **`initialization->addBody()` runs on *every request***, not at compile time (it
  is PHP emitted into `initialize()`) - keep it tiny.
- **A `getenv()`/env-derived parameter is baked at compile time** unless it is a
  *dynamic* parameter.
- User-facing how-to (NEON syntax, autowiring rules, service-definition patterns,
  decorator/search/di sections, extension-development lifecycle, generated
  factories) is manual material and lives in the public web docs, not here.
