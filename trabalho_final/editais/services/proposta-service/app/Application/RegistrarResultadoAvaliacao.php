<?php

namespace App\Application;

use App\Domain\Proposta\PropostaRepository;
use App\Domain\Proposta\StatusProposta;
use Illuminate\Support\Facades\DB;

/**
 * Use Case disparado pelo consumer de `proposta.avaliada` (Kafka): atualiza o
 * status do aggregate quando o avaliacao-service conclui o parecer.
 * A transição de estado é do aggregate — o consumer só traduz o evento.
 */
final class RegistrarResultadoAvaliacao
{
    public function __construct(private readonly PropostaRepository $propostas)
    {
    }

    public function executar(string $propostaId, string $resultado, string $parecer): void
    {
        $proposta = $this->propostas->porId($propostaId);
        if ($proposta === null) {
            throw new \RuntimeException("Proposta {$propostaId} não encontrada.");
        }

        $proposta->registrarResultado(StatusProposta::from($resultado), $parecer);

        DB::transaction(fn () => $this->propostas->salvar($proposta));
    }
}
