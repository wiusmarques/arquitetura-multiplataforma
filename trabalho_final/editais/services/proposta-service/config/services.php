<?php

return [
    // Read model público dos editais — usado para validar se o edital aceita
    // propostas antes de submeter.
    'edital' => ['base' => env('EDITAL_SERVICE_URL', 'http://edital-service:8001')],

    // Stub ilustrativo do gateway de pagamento, hospedado no gestao-interna.
    'pagamento' => ['base' => env('PAGAMENTO_SERVICE_URL', 'http://gestao-interna:8080')],
];
