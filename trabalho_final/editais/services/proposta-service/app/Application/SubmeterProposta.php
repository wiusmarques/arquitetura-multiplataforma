<?php

namespace App\Application;

use App\Application\Ports\Editais;
use App\Application\Ports\Outbox;
use App\Domain\Proposta\Events\PropostaSubmetida;
use App\Domain\Proposta\Proposta;
use App\Domain\Proposta\PropostaRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Use Case (Clean Architecture): orquestra o aggregate e os ports.
 *
 * Outbox Pattern na prática: a proposta e a linha do outbox são gravadas na
 * MESMA transação — ou as duas entram, ou nenhuma. O relay (outbox:relay)
 * publica no Kafka depois, resolvendo o problema da escrita dupla sem
 * transação distribuída.
 */
final class SubmeterProposta
{
    public function __construct(
        private readonly PropostaRepository $propostas,
        private readonly Outbox $outbox,
        private readonly Editais $editais,
    ) {
    }

    public function executar(
        string $editalId,
        string $proponente,
        string $titulo,
        string $descricao,
        string $retornoEsperado,
        DateTimeImmutable $dataInicio,
        int $mesesExecucao,
    ): Proposta {
        // Pré-condição que depende de outro contexto: o edital precisa existir,
        // estar publicado e dentro da vigência (consulta ao query side).
        $edital = $this->editais->porId($editalId);
        if ($edital === null) {
            throw new DomainException('Edital não encontrado.');
        }
        if (($edital['status'] ?? '') !== 'publicado') {
            throw new DomainException('O edital não está aberto para propostas.');
        }
        $encerramento = $edital['data_encerramento'] ?? null;
        if ($encerramento !== null && new DateTimeImmutable($encerramento) < new DateTimeImmutable('today')) {
            throw new DomainException('O prazo de submissão deste edital já encerrou.');
        }

        $proposta = Proposta::submeter(
            $editalId, $proponente, $titulo, $descricao,
            $retornoEsperado, $dataInicio, $mesesExecucao,
        );
        $evento = new PropostaSubmetida($proposta);

        DB::transaction(function () use ($proposta, $evento) {
            $this->propostas->salvar($proposta);
            $this->outbox->registrar(PropostaSubmetida::TOPICO, $proposta->id, $evento->payload());
        });

        return $proposta;
    }
}
