<?php

// Database per service: este serviço entra no PostgreSQL com a role `proposta`,
// cujo search_path é o schema `propostas` — ele não enxerga os schemas dos
// outros serviços (ver docker/postgres/init-schemas.sql na raiz do monorepo).
return [
    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', 'postgres'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'editais'),
            'username' => env('DB_USERNAME', 'proposta'),
            'password' => env('DB_PASSWORD', 'proposta'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => env('DB_SCHEMA', 'propostas'),
            'sslmode' => 'prefer',
        ],
    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],
];
