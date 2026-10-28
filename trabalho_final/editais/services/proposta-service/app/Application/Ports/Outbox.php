<?php

namespace App\Application\Ports;

/**
 * Port do Outbox Pattern: o Use Case pede "registre este evento
 * na mesma transação da escrita" sem saber que existe uma tabela outbox nem
 * um relay — isso é detalhe da Infrastructure.
 */
interface Outbox
{
    public function registrar(string $topico, string $chave, array $payload): void;
}
