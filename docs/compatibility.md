# Elasticsearch Version Compatibility

This document covers version-specific features and compatibility notes for Elasticsearch 8.x and 9.x.

## Supported Versions

| Component | Supported Versions |
|-----------|-------------------|
| Elasticsearch | 8.x, 9.x |
| PHP | 8.1+ |
| Laravel | 10, 11, 12 |
| elasticsearch-php client | ^8.0 \|\| ^9.0 |

## Feature Availability by Version

### Specialized Queries

| Query | Minimum ES Version | Notes |
|-------|-------------------|-------|
| `Query::knn()` | 8.8 | Top-level kNN search |
| `Query::sparseVector()` | 8.11 | Recommended for ELSER models |
| `Query::semantic()` | 8.14 | Semantic text field search |
| `Query::textExpansion()` | 8.8 | **Deprecated in 8.15**, use `sparseVector()` |

### Search Features

| Feature | Minimum ES Version | Notes |
|---------|-------------------|-------|
| Point in Time (PIT) | 7.10 | Used for cursor pagination |
| `search_after` | 5.0 | Deep pagination |
| `runtime_mappings` | 7.11 | Runtime fields |
| `track_total_hits` | 7.0 | Accurate total counts |

## ES 8.x to 9.x Migration Notes

### Breaking Changes in ES 9.x

#### 1. random_score Default Field Changed

In ES 9.x, the default field for `random_score` changed from `_id` to `_seq_no`.

**Recommendation:** Always specify the `field` explicitly for consistent behavior:

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

- **Elasticsearch:** 8.19.x, 9.3.x
- **PHP:** 8.1, 8.2, 8.3, 8.4
- **Laravel:** 10, 11, 12

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

## Known Limitations

1. **No version-specific branching:** All features are available regardless of ES version. Using features on incompatible versions will result in ES errors.

2. **Scroll API not implemented:** The package uses Point in Time (PIT) with `search_after` for deep pagination, which is the modern recommended approach.

3. **No legacy mapping types:** The package does not support legacy `_type` mappings removed in ES 8.x.
