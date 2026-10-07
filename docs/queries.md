# Queries

This document covers all available query types. Queries are created using the `Query` factory class.

```php
use Jackardios\EsScoutDriver\Support\Query;
```

## Table of Contents

- [Term-Level Queries](#term-level-queries)
- [Full-Text Queries](#full-text-queries)
- [Compound Queries](#compound-queries)
- [Geo Queries](#geo-queries)
- [Joining Queries](#joining-queries)
- [Specialized Queries](#specialized-queries)
- [Raw Query](#raw-query)

---

## Term-Level Queries

Term-level queries search for exact values in structured data.

### term

Find documents with an exact term in a field:

```php
Query::term('status', 'published')

Query::term('status', 'published')
    ->boost(1.5)
    ->caseInsensitive(true)
```

### terms

Find documents matching any of the specified terms:

```php
Query::terms('status', ['published', 'pending'])

Query::terms('status', ['published', 'pending'])
    ->boost(1.2)
```

### range

Find documents with values within a range:

```php
// Numeric range
Query::range('price')
    ->gte(10)
    ->lte(100)

// Date range
Query::range('created_at')
    ->gte('2024-01-01')
    ->lt('2025-01-01')
    ->format('yyyy-MM-dd')
    ->timeZone('+03:00')

// All range options
Query::range('field')
    ->gt(value)      // Greater than
    ->gte(value)     // Greater than or equal
    ->lt(value)      // Less than
    ->lte(value)     // Less than or equal
    ->boost(1.5)
    ->format('yyyy-MM-dd')
    ->timeZone('+00:00')
    ->relation('intersects') // For range fields: intersects, contains, within
```

A bound may be a `DateTimeInterface` (Carbon included):

```php
Query::range('created_at')->gte(now()->subDay())->lt(now())
// {"gte": "2024-05-01T09:30:00.123+03:00", "lt": "2024-05-02T09:30:00.123+03:00"}
```

It is sent as ISO 8601 with milliseconds and the UTC offset of the object, so it names the same instant whatever
the timezone of the object; `timeZone()` is for bounds without an offset. Strings and numbers are sent as given.

- Microseconds are cut off, as a `date` field stores milliseconds. For a `date_nanos` field pass a string
  (`$date->format('Y-m-d\TH:i:s.uP')`).
- The value parses with `strict_date_optional_time`, the default format of a `date` field. For a field mapped with
  another format (`yyyy-MM-dd HH:mm:ss`, `epoch_second`...) add `->format('strict_date_optional_time')` to the
  query, or pass a string in the field's own format.

### exists

Find documents where a field exists:

```php
Query::exists('email')

Query::exists('email')->boost(2.0)
```

### prefix

Find documents with terms starting with a prefix:

```php
Query::prefix('title', 'ela')

Query::prefix('title', 'ela')
    ->caseInsensitive(true)
    ->rewrite('constant_score')
    ->boost(2.0)
```

### wildcard

Find documents matching a wildcard pattern:

```php
Query::wildcard('title', 'ela*search')

Query::wildcard('title', '*search*')
    ->caseInsensitive(true)
    ->rewrite('constant_score')
    ->boost(1.5)
```

### regexp

Find documents matching a regular expression:

```php
Query::regexp('title', 'elast.*')

Query::regexp('title', 'elast[ic]+')
    ->caseInsensitive(true)
    ->maxDeterminizedStates(10000)
    ->rewrite('constant_score')
```

### fuzzy

Find documents with terms similar to the search term:

```php
Query::fuzzy('title', 'elastcsearch')

Query::fuzzy('title', 'elastcsearch')
    ->fuzziness('AUTO')
    ->maxExpansions(50)
    ->prefixLength(2)
    ->transpositions(true)
    ->rewrite('constant_score')
```

### ids

Find documents by their IDs:

```php
Query::ids(['1', '2', '3'])
```

---

## Full-Text Queries

Full-text queries are used for searching analyzed text fields.

### match

Standard full-text search:

```php
Query::match('title', 'elasticsearch guide')

Query::match('title', 'elasticsearch guide')
    ->operator('and')           // 'and' or 'or' (default)
    ->fuzziness('AUTO')
    ->prefixLength(2)
    ->maxExpansions(50)
    ->fuzzyTranspositions(true)
    ->lenient(true)
    ->analyzer('english')
    ->minimumShouldMatch('75%')
    ->zeroTermsQuery('all')
    ->autoGenerateSynonymsPhraseQuery(true)
    ->boost(2.0)
```

### multiMatch

Search across multiple fields:

```php
Query::multiMatch(['title', 'description'], 'search text')

Query::multiMatch(['title^3', 'description'], 'search text')
    ->type('best_fields')       // best_fields, most_fields, cross_fields, phrase, phrase_prefix, bool_prefix
    ->tieBreaker(0.3)
    ->operator('and')
    ->fuzziness('AUTO')
    ->analyzer('english')
    ->minimumShouldMatch('2')
```

### matchPhrase

Search for an exact phrase:

```php
Query::matchPhrase('title', 'quick brown fox')

Query::matchPhrase('title', 'quick brown fox')
    ->slop(2)                   // Allow terms to be this far apart
    ->analyzer('english')
    ->zeroTermsQuery('all')
```

### matchPhrasePrefix

Search for a phrase with prefix matching on the last term:

```php
Query::matchPhrasePrefix('title', 'quick brown f')

Query::matchPhrasePrefix('title', 'quick brown f')
    ->maxExpansions(50)
    ->slop(2)
    ->analyzer('english')
```

### queryString

Search using Lucene query syntax:

```php
Query::queryString('title:elasticsearch AND status:published')

Query::queryString('(quick OR brown) AND fox')
    ->defaultField('title')     // Or ->fields(['title^2', 'description']), not both

Query::queryString('(quick OR brown) AND fox')
    ->fields(['title^2', 'description'])
    ->defaultOperator('AND')
    ->analyzer('english')
    ->fuzziness('AUTO')
    ->fuzzyMaxExpansions(50)
    ->fuzzyPrefixLength(0)
    ->fuzzyTranspositions(true)
    ->fuzzyRewrite('constant_score')
    ->allowLeadingWildcard(true)
    ->analyzeWildcard(true)
    ->autoGenerateSynonymsPhraseQuery(true)
    ->boost(1.5)
    ->lenient(true)
    ->minimumShouldMatch('2')
    ->quoteFieldSuffix('.exact')
    ->phraseSlop(3)
    ->rewrite('constant_score')
```

### simpleQueryString

Simplified query syntax, more forgiving of errors:

```php
Query::simpleQueryString('elasticsearch | guide')

Query::simpleQueryString('"quick brown" + fox')
    ->fields(['title^2', 'description'])
    ->defaultOperator('AND')
    ->analyzer('english')
    ->flags('AND|OR|PREFIX')    // Enabled operators
    ->analyzeWildcard(true)
    ->autoGenerateSynonymsPhraseQuery(true)
    ->fuzzyMaxExpansions(50)
    ->fuzzyPrefixLength(0)
    ->fuzzyTranspositions(true)
    ->lenient(true)
    ->minimumShouldMatch('2')
    ->quoteFieldSuffix('.exact')
```

### combinedFields

Search across multiple text fields as if they were one combined field (ES 7.13+):

```php
Query::combinedFields(['title', 'description', 'content'], 'search text')

Query::combinedFields(['title^2', 'description'], 'search text')
    ->operator('and')                           // 'and' or 'or' (default)
    ->minimumShouldMatch('75%')
    ->zeroTermsQuery('none')                    // 'none' or 'all'
    ->autoGenerateSynonymsPhraseQuery(true)
    ->boost(1.5)
```

Unlike `multiMatch`, `combinedFields` treats all fields as having the same analyzer and combines term frequencies across fields for more accurate BM25 scoring. Best for searching across multiple text fields that should be treated as one logical field.

---

## Compound Queries

Compound queries wrap other queries to combine or modify their behavior.

### bool

Combine multiple queries with boolean logic:

```php
Query::bool()
    ->must(Query::match('title', 'elasticsearch'), Query::match('author', 'john'))
    ->should(Query::term('featured', true))
    ->filter(Query::range('price')->lte(100))
    ->mustNot(Query::term('status', 'draft'))
    ->minimumShouldMatch(1)
    ->boost(1.5)
```

`must()`, `mustNot()`, `should()` and `filter()` append every query they receive. `addMust()`, `addMustNot()`,
`addShould()` and `addFilter()` append one query and take an optional key.

Using keyed clauses for later modification:

```php
$bool = Query::bool()
    ->addMust(Query::match('title', 'elasticsearch'), key: 'title_match')
    ->addFilter(Query::term('status', 'published'), key: 'status_filter');

// Remove a clause
$bool->removeMust('title_match');

// Check if clause exists
if ($bool->hasClause('filter', 'status_filter')) {
    // ...
}
```

Set clauses (replace all):

```php
Query::bool()
    ->setMust(Query::match('title', 'test'))
    ->setFilter(Query::term('status', 'active'), Query::range('price')->gte(10));
```

### nested

Search within nested objects:

```php
Query::nested('comments', Query::match('comments.text', 'great'))

Query::nested('comments', Query::bool()
    ->must(Query::match('comments.text', 'great'), Query::term('comments.author', 'john'))
)
    ->scoreMode('avg')          // avg, max, min, sum, none
    ->ignoreUnmapped(true)

// With inner hits
Query::nested('comments', Query::match('comments.text', 'great'))
    ->innerHits([
        'size' => 3,
        'highlight' => ['fields' => ['comments.text' => new \stdClass()]],
    ])
```

### functionScore

Modify scores using functions:

```php
Query::functionScore(Query::match('title', 'elasticsearch'))
    ->addFunction([
        'script_score' => [
            'script' => ['source' => "_score * doc['popularity'].value"],
        ],
    ])
    ->functionScoreMode('multiply') // multiply, sum, avg, first, max, min
    ->boostMode('multiply')     // multiply, replace, sum, avg, max, min
    ->maxBoost(10)
    ->minScore(1)

// With weight function
Query::functionScore(Query::matchAll())
    ->addFunction(['weight' => 2.0, 'filter' => Query::term('featured', true)])
    ->addFunction(['weight' => 1.5, 'filter' => Query::term('premium', true)])

// With field value factor
Query::functionScore(Query::matchAll())
    ->addFunction([
        'field_value_factor' => [
            'field' => 'popularity',
            'modifier' => 'log1p',
            'factor' => 2,
            'missing' => 1,
        ],
    ])

// With decay function
Query::functionScore(Query::matchAll())
    ->addFunction([
        'linear' => [
            'date' => [
                'origin' => 'now',
                'scale' => '10d',
                'decay' => 0.5,
            ],
        ],
    ])

// With random score
Query::functionScore(Query::matchAll())
    ->addFunction(['random_score' => ['seed' => 12345, 'field' => '_seq_no']])
```

### disMax

Return the best matching query:

```php
Query::disMax([
    Query::match('title', 'search'),
    Query::match('description', 'search'),
])
    ->tieBreaker(0.3)           // How much non-best queries contribute
    ->boost(1.5)
```

### boosting

Boost or demote specific results:

```php
Query::boosting(
    positive: Query::match('title', 'elasticsearch'),
    negative: Query::term('status', 'outdated')
)
    ->negativeBoost(0.5)        // Factor to reduce negative matches
```

### constantScore

Wrap a filter and assign a constant score:

```php
Query::constantScore(Query::term('status', 'published'))
    ->boost(1.5)
```

---

## Geo Queries

Geo queries search for documents based on geographic location.

### geoDistance

Find documents within a distance from a point:

```php
Query::geoDistance('location', 52.3676, 4.9041, '10km')

Query::geoDistance('location', 52.3676, 4.9041, '10km')
    ->distanceType('arc')       // arc (accurate) or plane (fast)
    ->validationMethod('strict')
```

### geoBoundingBox

Find documents within a bounding box:

```php
Query::geoBoundingBox(
    'location',
    topLeftLat: 52.5,
    topLeftLon: 4.5,
    bottomRightLat: 52.0,
    bottomRightLon: 5.5
)
    ->validationMethod('strict')
```

### geoShape

Find documents that relate to a shape:

```php
// With inline shape
Query::geoShape('location')
    ->shape([
        'type' => 'envelope',
        'coordinates' => [[4.5, 52.5], [5.5, 52.0]],
    ])
    ->relation('within')        // within, contains, intersects, disjoint

// With indexed shape
Query::geoShape('location')
    ->indexedShape('shapes_index', 'shape_id')
    ->relation('intersects')
```

---

## Joining Queries

Joining queries search parent-child relationships.

### hasChild

Find parent documents with matching children:

```php
Query::hasChild('comment', Query::match('text', 'great'))

Query::hasChild('comment', Query::match('text', 'great'))
    ->scoreMode('avg')          // avg, max, min, sum, none
    ->minChildren(1)
    ->maxChildren(10)
    ->ignoreUnmapped(true)
    ->innerHits(['size' => 5])
```

### hasParent

Find child documents with matching parents:

```php
Query::hasParent('post', Query::match('title', 'elasticsearch'))

Query::hasParent('post', Query::match('title', 'elasticsearch'))
    ->score(true)               // Include parent score
    ->ignoreUnmapped(true)
    ->innerHits(['size' => 1])
```

### parentId

Find children of a specific parent:

```php
Query::parentId('comment', 'parent_post_id')

Query::parentId('comment', 'parent_post_id')
    ->ignoreUnmapped(true)
```

---

## Specialized Queries

### matchAll

Match all documents:

```php
Query::matchAll()

Query::matchAll()->boost(1.5)
```

### matchNone

Match no documents:

```php
Query::matchNone()
```

### moreLikeThis

Find documents similar to provided text or documents:

```php
// Similar to text
Query::moreLikeThis(['title', 'description'], 'This is sample text')

// Similar to documents
Query::moreLikeThis(['title'], [
    ['_id' => '1'],
    ['_id' => '2'],
])

// With options
Query::moreLikeThis(['title', 'description'], 'Sample text')
    ->minTermFreq(1)
    ->maxQueryTerms(25)
    ->minDocFreq(1)
    ->maxDocFreq(100000)
    ->minWordLength(3)
    ->maxWordLength(20)
    ->stopWords(['the', 'is', 'a'])
    ->analyzer('english')
    ->minimumShouldMatch('30%')
    ->include(true)             // Include the input documents
    ->boost(1.5)
```

### scriptScore

Custom scoring with a script:

```php
Query::scriptScore(
    Query::match('title', 'elasticsearch'),
    ['source' => "_score * doc['popularity'].value"]
)

Query::scriptScore(
    Query::matchAll(),
    [
        'source' => "cosineSimilarity(params.query_vector, 'embedding') + 1.0",
        'params' => ['query_vector' => [0.1, 0.2, 0.3]],
    ]
)
    ->minScore(0.5)
    ->boost(2.0)
```

### pinned

Pin specific documents to the top of results:

```php
Query::pinned(Query::match('title', 'elasticsearch'))
    ->ids(['doc_1', 'doc_2', 'doc_3'])

// With docs from specific index
Query::pinned(Query::matchAll())
    ->docs([
        ['_index' => 'books', '_id' => '1'],
        ['_index' => 'articles', '_id' => '2'],
    ])
```

### knn

K-nearest neighbors vector search:

```php
Query::knn('embedding', [0.12, -0.34, 0.56, 0.78], k: 10)
    ->numCandidates(100)
    ->similarity(0.8)
    ->filter(Query::term('status', 'published'))
    ->boost(5.0)
```

`numCandidates()` takes 1 to 10000 and at least `k`. Without it, the query sends twice `k`, at least 100 and at most
10000; for a `k` above 10000 it sends none and Elasticsearch picks the number itself.

The `knn` query takes `k` from Elasticsearch 8.15. On 8.12–8.14 leave `k` out: Elasticsearch collects
`num_candidates` per shard and the size of the search decides how many hits come back. Without `k` the query sends
`num_candidates` only when `numCandidates()` is called (8.12 requires it; 8.13 and 8.14 default it to 1.5 times the
size). A query without `k` stays valid on 8.15 and later, where Elasticsearch defaults `k` to `num_candidates` and
`num_candidates` to 1.5 times the size:

```php
Book::searchQuery(Query::knn('embedding', $vector)->numCandidates(100))->size(10)->execute();
```

### semantic

Semantic search on a `semantic_text` field (ES 8.15+):

```php
Query::semantic('semantic_field', 'What is machine learning?')
```

### sparseVector

Sparse vector search (ES 8.15+):

```php
Query::sparseVector('ml.tokens')
    ->inferenceId('my-elser-model')
    ->query('What is Elasticsearch?')

// A semantic_text field brings its own inference endpoint
Query::sparseVector('content')
    ->query('What is Elasticsearch?')

// A precomputed vector, without query or inferenceId
Query::sparseVector('ml.tokens')
    ->queryVector(['elasticsearch' => 1.2, 'search' => 0.8])

// With pruning
Query::sparseVector('ml.tokens')
    ->inferenceId('my-model')
    ->query('search text')
    ->prune(true)
    ->pruningConfig(
        tokensFreqRatioThreshold: 5,
        tokensWeightThreshold: 0.4,
    )
```

### textExpansion

Text expansion query (deprecated in favor of sparseVector):

```php
Query::textExpansion('ml.tokens', 'my-elser-model')
    ->modelText('What is Elasticsearch?')
    ->pruningConfig(
        tokensFreqRatioThreshold: 5,
        tokensWeightThreshold: 0.4,
    )
```

---

## Raw Query

For any query not covered by the API or for complex scenarios:

```php
Query::raw([
    'match' => [
        'title' => [
            'query' => 'elasticsearch',
            'fuzziness' => 'AUTO',
        ],
    ],
])
```

---

## Extending Query

You can add custom query methods using macros:

```php
use Jackardios\EsScoutDriver\Support\Query;

// In a service provider
Query::macro('fullTextSearch', function (string $text, array $fields = ['title', 'description']) {
    return Query::multiMatch($fields, $text)
        ->type('best_fields')
        ->fuzziness('AUTO')
        ->operator('and');
});

// Usage
$query = Query::fullTextSearch('search text');
```
