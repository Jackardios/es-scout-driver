<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Refresh Documents
    |--------------------------------------------------------------------------
    |
    | Whether to refresh the index after each write operation. This makes
    | documents immediately available for search but may impact performance.
    |
    */
    'refresh_documents' => env('ELASTIC_REFRESH_DOCUMENTS', false),

    /*
    |--------------------------------------------------------------------------
    | Model Hydration Mismatch Strategy
    |--------------------------------------------------------------------------
    |
    | Controls behavior when Elasticsearch returns hits that cannot be hydrated
    | into Eloquent models (for example, when callbacks filter out rows).
    |
    | Supported values:
    | - ignore: silently skip missing models
    | - log:    log a warning and skip missing models
    | - exception: throw ModelHydrationMismatchException
    |
    */
    'model_hydration_mismatch' => env('ELASTIC_MODEL_HYDRATION_MISMATCH', 'ignore'),

    /*
    |--------------------------------------------------------------------------
    | Bulk Operation Failure Mode
    |--------------------------------------------------------------------------
    |
    | Controls behavior when bulk operations partially fail (some documents
    | fail while others succeed).
    |
    | Supported values:
    | - exception: throw BulkOperationException (default)
    | - log:       log an error and continue
    | - ignore:    silently ignore failures
    |
    */
    'bulk_failure_mode' => env('ELASTIC_BULK_FAILURE_MODE', 'exception'),

    /*
    |--------------------------------------------------------------------------
    | Scout Query Type
    |--------------------------------------------------------------------------
    |
    | Controls which Elasticsearch query type is used for Scout's basic
    | search (Model::search('query')). This does NOT affect SearchBuilder.
    |
    | Supported values:
    | - simple_query_string: safer, limited syntax (default)
    | - query_string:        full Lucene syntax (use with caution)
    |
    | Security note: "query_string" accepts full Lucene syntax including
    | field:value queries. If user input is passed directly to search(),
    | users could query unintended fields. Use "simple_query_string" when
    | search input comes from untrusted sources.
    |
    */
    'scout_query_type' => env('ELASTIC_SCOUT_QUERY_TYPE', 'simple_query_string'),
];
