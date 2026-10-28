<?php

// O app-web (SPA) chama o BFF de outra origem; autenticação é por header
// Authorization (Bearer), não por cookie — por isso credentials=false e
// origens liberadas no laboratório.
return [
    'paths' => ['v1/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 3600,
    'supports_credentials' => false,
];
