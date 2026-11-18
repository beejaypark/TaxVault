<?php

return [

    // The provider used when a document is uploaded directly to TaxVault.
    'default_provider' => env('DOCUMENT_STORAGE_PROVIDER', 'taxvault'),

    'sync_statuses' => [
        'local',
        'synced',
        'changed',
        'deleted',
        'error',
    ],

];
