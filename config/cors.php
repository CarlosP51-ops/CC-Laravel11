<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept', 'X-Requested-With', 'X-HTTP-Method-Override'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => false,
];
