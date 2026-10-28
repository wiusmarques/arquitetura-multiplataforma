<?php

return [
    'avaliacao' => ['base' => env('AVALIACAO_SERVICE_URL', 'http://avaliacao-service:8003')],

    // O acompanhamento de propostas pela administração consulta o
    // proposta-service direto (REST, rede interna — não passa pelo BFF).
    'proposta' => ['base' => env('PROPOSTA_SERVICE_URL', 'http://proposta-service:8002')],

    'keycloak' => [
        'issuer' => env('KEYCLOAK_ISSUER', 'http://idp.editais.local:8089/realms/editais'),
        'client_id' => env('KEYCLOAK_CLIENT_ID', 'gestao-interna'),
        'client_secret' => env('KEYCLOAK_CLIENT_SECRET', 'gestao-interna-secret'),
        'public_url' => env('GESTAO_PUBLIC_URL', 'http://gestao.editais.local:8080'),
    ],
];
