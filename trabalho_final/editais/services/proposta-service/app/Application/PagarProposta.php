<?php

namespace App\Application;

use App\Application\Ports\Editais;
use App\Application\Ports\Pagamentos;
use App\Domain\Proposta\Proposta;
use App\Domain\Proposta\PropostaRepository;
use App\Domain\Proposta\StatusProposta;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Executa (ou reexecuta) o pagamento de UMA proposta aprovada. O valor do
 * apoio é o valor_por_candidato do edital — a proposta não tem valor próprio.
 * Usado pelo worker de pagamentos em massa (resultado.publicado) e pela
 * ressolicitação manual da administração quando o gateway falha.
 */
final class PagarProposta
{
    public function __construct(
        private readonly PropostaRepository $propostas,
        private readonly Editais $editais,
        private readonly Pagamentos $pagamentos,
    ) {
    }

    public function executar(string $propostaId): Proposta
    {
        $proposta = $this->propostas->porId($propostaId);
        if ($proposta === null) {
            throw new DomainException('Proposta não encontrada.');
        }
        if ($proposta->status() !== StatusProposta::Aprovada) {
            throw new DomainException("Só proposta aprovada recebe pagamento (está '{$proposta->status()->value}').");
        }

        $edital = $this->editais->porId($proposta->editalId);
        $valor = (float) ($edital['valor_por_candidato'] ?? 0);
        if ($valor <= 0) {
            throw new DomainException('Edital sem valor por candidato definido.');
        }

        $efetuado = $this->pagamentos->executar($proposta->id, $proposta->proponente, $valor);

        $proposta->registrarPagamento($efetuado);
        DB::transaction(fn () => $this->propostas->salvar($proposta));

        return $proposta;
    }
}
