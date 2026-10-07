# Elasticsearch Version Compatibility

This document covers version-specific features and compatibility notes for Elasticsearch 8.x and 9.x.

## Supported Versions

| Component | Supported Versions |
|-----------|-------------------|
| Elasticsearch | 8.x, 9.x |
| PHP | 8.2+ (Laravel 13 requires 8.3+) |
| Laravel | 12, 13 (10 and 11: the 0.x line) |
| Laravel Scout | 10.24+, 11 |
| elasticsearch-php client | ^8.0 \|\| ^9.0, the same major version as the server (see [PHP Client Compatibility](#php-client-compatibility)) |

## Feature Availability by Version

### Specialized Queries

| Query | Minimum ES Version | Notes |
|-------|-------------------|-------|
| `Query::knn()` without `k` | 8.12 | `knn` query; the size of the search bounds the hits. 8.12 requires `numCandidates()`, later versions default it; from 8.15 `k` defaults to `num_candidates` |
| `Query::knn()` with `k` | 8.15 | The `knn` query takes `k` from 8.15; earlier versions answer 400 |
| `Query::sparseVector()` | 8.15 | Recommended for ELSER models |
| `Query::semantic()` | 8.15 | `semantic_text` field search |
| `Query::textExpansion()` | 8.8 | **Deprecated in 8.15**, use `sparseVector()`; `pruningConfig()` needs 8.13 |

### Search Features

| Feature | Minimum ES Version | Notes |
|---------|-------------------|-------|
| `SearchBuilder::knn()` (top-level knn search) | 8.4 | Takes `k`, `num_candidates` and `filter` in every version since |
| Point in Time (PIT) | 7.10 | Used for cursor pagination |
| `search_after` | 5.0 | Deep pagination |
| `runtime_mappings` | 7.11 | Runtime fields |
| `track_total_hits` | 7.0 | Accurate total counts |

## ES 8.x to 9.x Migration Notes

### Breaking Changes in ES 9.x

#### 1. random_score Default Field Changed

With a `seed` and no `field`, ES 8.x reads `_id`, whose fielddata is disabled by default, and fails; ES 9.x reads
`_seq_no`.

**Recommendation:** Always specify the `field` with a seed. Use `_seq_no` or a unique field with doc values, not `_id`:

```php
Query::functionScore(Query::matchAll())
    ->addFunction(['random_score' => ['seed' => 12345, 'field' => '_seq_no']])
```

#### 2. Date Histogram on Boolean Fields Removed

ES 9.x no longer supports Date Histogram aggregations on boolean fields.

**Workaround:** Use Terms aggregation for boolean fields instead.

#### 3. Stricter Bulk Request Parsing

ES 9.x enforces strict JSON parsing in bulk requests. Malformed JSON that was previously tolerated will now be rejected.

**Impact:** None for this package (uses proper JSON serialization).

#### 4. Timeout Responses Changed

ES 9.x returns HTTP 429 for timeouts instead of 5xx errors.

**Impact:** Update error handling if you check for specific HTTP status codes.

### Deprecated Features

#### TextExpansionQuery (Deprecated in 8.15)

```php
// Deprecated
Query::textExpansion('ml.tokens', 'my-elser-model')
    ->modelText('search query');

// Use instead
Query::sparseVector('ml.tokens')
    ->inferenceId('my-elser-model')
    ->query('search query');
```

## Version Detection

The package does not perform runtime version detection. Features requiring specific ES versions will fail with appropriate error messages from Elasticsearch if used on incompatible versions.

## Testing Matrix

The package is tested against the following matrix:

- **Elasticsearch:** 8.19.x with the 8.x client, 9.5.x with the 9.x client
- **PHP:** 8.2, 8.3, 8.4, 8.5 (Laravel 13 needs 8.3+)
- **Laravel:** 12, 13 (Scout 11 everywhere, Scout 10 on one leg per Laravel version, lowest dependencies on two legs)

The integration suite has no test of the `knn`, `sparse_vector`, `semantic` and `text_expansion` queries: their
minimum versions above come from the Elasticsearch reference and release notes.

Run the full test matrix locally:

```bash
make test-matrix      # ES versions only
make test-full-matrix # Full PHP × Laravel × ES matrix
```

## PHP Client Compatibility

### elasticsearch-php 8.x vs 9.x

| Feature | 8.x | 9.x |
|---------|-----|-----|
| Minimum PHP | 8.0 | 8.1 |
| HTTP Client | Guzzle | Built-in cURL |
| API | Stable | Stable (minimal changes) |

The package supports both client versions via `"elasticsearch/elasticsearch": "^8.0 || ^9.0"`.

### The client major must match the server major

Composer installs the newest client the constraint allows, which is 9.x. The 9.x client sends
`compatible-with=9` in its `Accept` and `Content-Type` headers. Elastic pairs the 9.x client with 9.x servers and the
8.x client with 8.x servers; a 9.x client against an 8.x server is outside that pairing, is expected to be refused
by the server, and is not tested by this package.

Within a major version:

| Client | Server | Status |
|--------|--------|--------|
| newest 8.x | 8.19.x | Tested |
| lowest installable 8.x | 8.19.x | Tested |
| newest 9.x | 9.5.x | Tested |
| 8.x newer than the server (such as 8.19 with 8.12) | 8.x | Not tested |
| 9.x | 8.x | Not supported |

Elastic guarantees a client only against servers of the same or a newer minor version.

For an Elasticsearch 8 server, require the 8.x client in your application:

```bash
composer require elasticsearch/elasticsearch:^8.0
```

Without that line in your own `composer.json` a later `composer update` may move the client to 9.x, for example
when another package stops holding it at 8.x.

## Known Limitations

1. **No version-specific branching:** All features are available regardless of ES version. Using features on incompatible versions will result in ES errors.

2. **Scroll API not implemented:** The package uses Point in Time (PIT) with `search_after` for deep pagination, which is the modern recommended approach.

3. **No legacy mapping types:** The package does not support legacy `_type` mappings removed in ES 8.x.
