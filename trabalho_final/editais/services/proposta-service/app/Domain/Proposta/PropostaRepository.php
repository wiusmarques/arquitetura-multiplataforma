<?php

namespace App\Domain\Proposta;

/**
 * Port de persistência (Repository do DDD): a interface mora no DOMÍNIO,
 * a implementação (Eloquent) mora na Infrastructure — inversão de dependência.
 */
interface PropostaRepository
{
    public function salvar(Proposta $proposta): void;

    public function porId(string $id): ?Proposta;

    /** @return Proposta[] */
    public function porProponente(string $proponente): array;

    /** @return Proposta[] */
    public function porEdital(string $editalId): array;
}
