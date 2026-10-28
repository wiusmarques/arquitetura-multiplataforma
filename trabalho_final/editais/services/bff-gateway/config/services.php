<?php

// Endereços internos dos serviços e do Keycloak. O BFF é o ÚNICO ponto que os
// clientes externos conhecem — estes endereços nunca vazam para fora.
return [
    'keycloak' => [
        // Issuer com a MESMA URL dentro e fora do Docker (alias de rede
        // idp.editais.local + porta 8089 idêntica) — ver docker-compose.yaml.
        'issuer' => env('KEYCLOAK_ISSUER', 'http://idp.editais.local:8089/realms/editais'),
    ],

    'edital' => ['base' => env('EDITAL_SERVICE_URL', 'http://edital-service:8001')],
    'proposta' => ['base' => env('PROPOSTA_SERVICE_URL', 'http://proposta-service:8002')],
    'avaliacao' => ['base' => env('AVALIACAO_SERVICE_URL', 'http://avaliacao-service:8003')],
];
