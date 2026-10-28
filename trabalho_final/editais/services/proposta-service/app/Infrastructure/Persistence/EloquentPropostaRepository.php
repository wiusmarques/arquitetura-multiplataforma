<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Proposta\Proposta;
use App\Domain\Proposta\PropostaRepository;
use App\Domain\Proposta\StatusProposta;
use DateTimeImmutable;

/** Adapter Eloquent do Repository: traduz aggregate <-> linha da tabela. */
class EloquentPropostaRepository implements PropostaRepository
{
    public function salvar(Proposta $proposta): void
    {
        PropostaModel::updateOrCreate(
            ['id' => $proposta->id],
            [
                'edital_id' => $proposta->editalId,
                'proponente' => $proposta->proponente,
                'titulo' => $proposta->titulo,
                'descricao' => $proposta->descricao,
                'retorno_esperado' => $proposta->retornoEsperado,
                'data_inicio' => $proposta->dataInicio->format('Y-m-d'),
                'meses_execucao' => $proposta->mesesExecucao,
                'status' => $proposta->status()->value,
                'parecer' => $proposta->parecer(),
                'submetida_em' => $proposta->submetidaEm->format('Y-m-d H:i:sP'),
            ],
        );
    }

    public function porId(string $id): ?Proposta
    {
        $linha = PropostaModel::find($id);

        return $linha ? $this->paraDominio($linha) : null;
    }

    public function porProponente(string $proponente): array
    {
        return PropostaModel::where('proponente', $proponente)
            ->orderBy('submetida_em')
            ->get()
            ->map(fn (PropostaModel $linha) => $this->paraDominio($linha))
            ->all();
    }

    private function paraDominio(PropostaModel $linha): Proposta
    {
        return Proposta::reconstituir(
            id: $linha->id,
            editalId: $linha->edital_id,
            proponente: $linha->proponente,
            titulo: $linha->titulo,
            descricao: (string) $linha->descricao,
            retornoEsperado: (string) $linha->retorno_esperado,
            dataInicio: new DateTimeImmutable($linha->data_inicio),
            mesesExecucao: (int) $linha->meses_execucao,
            status: StatusProposta::from($linha->status),
            submetidaEm: new DateTimeImmutable($linha->submetida_em),
            parecer: $linha->parecer,
        );
    }
}
