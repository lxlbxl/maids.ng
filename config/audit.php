<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Audit Log Masking
    |--------------------------------------------------------------------------
    | Field name fragments to mask in logged request/response bodies.
    | Any key containing these strings (case-insensitive) will be replaced
    | with '*****MASKED*****' before the entry is persisted.
    */
    'mask_fields' => [
        'password',
        'secret',
        'token',
        'key',
        'authorization',
        'auth',
        'card',
        'cvv',
        'pin',
    ],
];
