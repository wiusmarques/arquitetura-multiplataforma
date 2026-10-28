<?php

namespace App\Domain\Proposta\Events;

use App\Domain\Proposta\Proposta;

/** Domain Event: fato de negócio que os outros bounded contexts assinam. */
final class PropostaSubmetida
{
    public const TOPICO = 'proposta.submetida';

    public function __construct(public readonly Proposta $proposta)
    {
    }

    public function payload(): array
    {
        return [
            'proposta_id' => $this->proposta->id,
            'edital_id' => $this->proposta->editalId,
            'proponente' => $this->proposta->proponente,
            'titulo' => $this->proposta->titulo,
            'descricao' => $this->proposta->descricao,
            'retorno_esperado' => $this->proposta->retornoEsperado,
            'data_inicio' => $this->proposta->dataInicio->format('Y-m-d'),
            'meses_execucao' => $this->proposta->mesesExecucao,
            'submetida_em' => $this->proposta->submetidaEm->format(DATE_ATOM),
        ];
    }
}
