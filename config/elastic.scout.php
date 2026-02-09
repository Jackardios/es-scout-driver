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
];
