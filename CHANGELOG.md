# Changelog

## Unreleased (0.1.0)

First tagged release, cut from `bb72a59`. Supports Laravel 10–13 and Scout 10–11.

### Fixed

- Laravel 13: the `elastic` driver no longer fails with `Undefined property: EngineManager::$app`. Laravel 13 binds
  custom driver closures to the manager.
- Scout 11: `where()` clauses, including the `__soft_deleted` constraint Scout adds for soft-deleting models, build
  valid queries again. Scout 11 stores them as `field`/`operator`/`value` entries.
- The soft-delete filter no longer turns off the should clauses of a bool query that has no must or filter clause: such
  a query matched every document that was not trashed, and `deleteByQuery()`/`updateByQuery()` acted on all of them.
  The bool is wrapped in a must clause when a filter beside it would change what it matches.

### Added

- Scout 11 `where()` operators: `=`, `!=`/`<>`, `>`, `>=`, `<`, `<=`; dates are sent as ISO 8601.

### Changed

- The Illuminate components in use are required at Laravel 10–13, so Composer no longer installs the package on
  Laravel 9.
- `where($field, null)` matches documents without the field (`!=` matches documents with it) instead of sending a
  `term` query for `null`. Other operators, such as `like`, throw `InvalidArgumentException`.
- `laravel/scout` no longer allows the unreleased `^12.0`.
