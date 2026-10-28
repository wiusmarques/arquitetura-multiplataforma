<?php

namespace App\Domain\Proposta;

use DateTimeImmutable;
use DomainException;
use Ramsey\Uuid\Uuid;

/**
 * Aggregate Root do bounded context de Propostas (DDD tático).
 *
 * Entidade PURA: não estende Model, não conhece Eloquent, HTTP nem Kafka —
 * só PHP e as invariantes do negócio. É a camada mais interna da Clean
 * Architecture; tudo aponta para cá, ela não aponta para nada.
 *
 * Campos avaliados pelo parecer: titulo, descricao e retornoEsperado.
 * dataInicio e mesesExecucao são informativos (sem avaliação). O valor a
 * receber em caso de aprovação é o valor_por_candidato do edital — a
 * proposta não pede valor.
 */
final class Proposta
{
    private function __construct(
        public readonly string $id,
        public readonly string $editalId,
        public readonly string $proponente,
        public readonly string $titulo,
        public readonly string $descricao,
        public readonly string $retornoEsperado,
        public readonly DateTimeImmutable $dataInicio,
        public readonly int $mesesExecucao,
        private StatusProposta $status,
        public readonly DateTimeImmutable $submetidaEm,
        private ?string $parecer = null,
    ) {
    }

    /** Fábrica que garante as invariantes: proposta inválida não existe. */
    public static function submeter(
        string $editalId,
        string $proponente,
        string $titulo,
        string $descricao,
        string $retornoEsperado,
        DateTimeImmutable $dataInicio,
        int $mesesExecucao,
    ): self {
        if (trim($titulo) === '') {
            throw new DomainException('A proposta precisa de um título.');
        }
        if (trim($proponente) === '') {
            throw new DomainException('A proposta precisa de um proponente identificado.');
        }
        if (trim($descricao) === '') {
            throw new DomainException('A proposta precisa da descrição do projeto.');
        }
        if (trim($retornoEsperado) === '') {
            throw new DomainException('A proposta precisa do retorno esperado.');
        }
        if ($mesesExecucao < 1) {
            throw new DomainException('A execução precisa de pelo menos 1 mês.');
        }

        return new self(
            id: Uuid::uuid4()->toString(),
            editalId: $editalId,
            proponente: $proponente,
            titulo: trim($titulo),
            descricao: trim($descricao),
            retornoEsperado: trim($retornoEsperado),
            dataInicio: $dataInicio,
            mesesExecucao: $mesesExecucao,
            status: StatusProposta::Submetida,
            submetidaEm: new DateTimeImmutable(),
        );
    }

    /** Transição de estado controlada pelo aggregate — nunca por UPDATE solto. */
    public function registrarResultado(StatusProposta $resultado, string $parecer): void
    {
        if ($this->status !== StatusProposta::Submetida) {
            throw new DomainException("Proposta {$this->id} já foi avaliada ({$this->status->value}).");
        }
        if (! in_array($resultado, [StatusProposta::Aprovada, StatusProposta::Reprovada], true)) {
            throw new DomainException('Resultado de avaliação deve ser aprovada ou reprovada.');
        }
        $this->status = $resultado;
        $this->parecer = $parecer;
    }

    public function status(): StatusProposta
    {
        return $this->status;
    }

    public function parecer(): ?string
    {
        return $this->parecer;
    }

    /** Reconstitui o aggregate a partir da persistência (uso do Repository). */
    public static function reconstituir(
        string $id,
        string $editalId,
        string $proponente,
        string $titulo,
        string $descricao,
        string $retornoEsperado,
        DateTimeImmutable $dataInicio,
        int $mesesExecucao,
        StatusProposta $status,
        DateTimeImmutable $submetidaEm,
        ?string $parecer,
    ): self {
        return new self($id, $editalId, $proponente, $titulo, $descricao, $retornoEsperado,
            $dataInicio, $mesesExecucao, $status, $submetidaEm, $parecer);
    }
}
