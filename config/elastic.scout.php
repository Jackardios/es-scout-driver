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
];
