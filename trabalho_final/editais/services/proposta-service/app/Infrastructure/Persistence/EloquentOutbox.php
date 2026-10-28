<?php

namespace App\Infrastructure\Persistence;

use App\Application\Ports\Outbox;

/**
 * Adapter do Outbox: um INSERT na tabela outbox, dentro da transação aberta
 * pelo Use Case. Quem leva ao Kafka é o relay (comando outbox:relay) —
 * nunca o request do usuário.
 */
class EloquentOutbox implements Outbox
{
    public function registrar(string $topico, string $chave, array $payload): void
    {
        OutboxModel::create([
            'topico' => $topico,
            'chave' => $chave,
            'payload' => $payload,
            'criado_em' => now(),
        ]);
    }
}
