<?php

namespace App\Infrastructure\Http;

use App\Application\Ports\Pagamentos;
use Illuminate\Support\Facades\Http;

/** Adapter do port Pagamentos: chama o stub de gateway no gestao-interna. */
class HttpPagamentos implements Pagamentos
{
    public function executar(string $propostaId, string $proponente, float $valor): bool
    {
        return (bool) Http::timeout(5)
            ->post(config('services.pagamento.base').'/v1/pagamentos', [
                'proposta_id' => $propostaId,
                'proponente' => $proponente,
                'valor' => $valor,
            ])
            ->throw()
            ->json('efetuado');
    }
}
