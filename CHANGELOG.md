# Changelog

## Unreleased (1.0.0-rc.1)

The 1.x line requires Laravel 12 or 13. Laravel 10 and 11 stay on 0.x.

### Upgrading from 0.x

- Requirements: PHP 8.2, Laravel 12 or 13, Scout 10.24 or 11.
- `BoolQuery::addMustMany()`, `addMustNotMany()`, `addShouldMany()` and `addFilterMany()` are removed. Call
  `must()`, `mustNot()`, `should()` and `filter()`, which take the same arguments.
- The soft-delete mode moves from `BoolQuery` to `SearchBuilder`: replace `$builder->boolQuery()->withTrashed()` with
  `$builder->withTrashed()`, and the same for `onlyTrashed()`, `excludeTrashed()`, `softDelete()` and
  `getSoftDeleteMode()`.
- `Engine` is final and its members are private. Implement `EngineInterface` or wrap the engine instead of extending
  it.
- `SearchBuilder::__construct()`, `query()`, `rescore()`, `postFilter()` and `Searchable::searchQuery()` type their
  `$query` parameter as `QueryInterface|Closure|array` (nullable in the constructor and `searchQuery()`). Other values
  throw a `TypeError` at the call.
- Classes and methods marked `@internal` are outside the compatibility promise; see "Backward Compatibility" in the
  README.

### Added

- `BoolQuery::must()`, `mustNot()`, `should()` and `filter()` append any number of clauses, like their `SearchBuilder`
  counterparts.
- `SearchBuilder::softDelete()`, `withTrashed()`, `onlyTrashed()`, `excludeTrashed()` and `getSoftDeleteMode()`.
- `PrefixQuery::boost()` and `ExistsQuery::boost()`.

### Fixed

- `SearchBuilder::clearBoolQuery()` no longer resets the soft-delete mode to excluding trashed documents;
  `clearAll()` still does.
- An empty `BoolQuery` keeps its boost: it is sent as `match_all` with the boost instead of dropping it.
- `BoolQuery::hasClause()` and `getClause()` throw `InvalidArgumentException` for a section other than `must`,
  `must_not`, `should` or `filter` (a typo such as `mustNot` returned false or null).

### Changed

- `Engine::getClient()` returns `Client` (the interface still allows `null` for `NullEngine`).
- CI covers PHP 8.2–8.5 on Laravel 12 and 13, lowest dependencies, Elasticsearch 8.19.22 and 9.5.3, a weekly run and
  `composer audit`.

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
