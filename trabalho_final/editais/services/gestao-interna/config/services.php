<?php

return [
    'avaliacao' => ['base' => env('AVALIACAO_SERVICE_URL', 'http://avaliacao-service:8003')],

    'keycloak' => [
        'issuer' => env('KEYCLOAK_ISSUER', 'http://idp.editais.local:8089/realms/editais'),
        'client_id' => env('KEYCLOAK_CLIENT_ID', 'gestao-interna'),
        'client_secret' => env('KEYCLOAK_CLIENT_SECRET', 'gestao-interna-secret'),
        'public_url' => env('GESTAO_PUBLIC_URL', 'http://gestao.editais.local:8080'),
    ],
];
