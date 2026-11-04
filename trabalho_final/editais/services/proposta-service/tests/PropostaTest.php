<?php

/**
 * Testes do Aggregate Root Proposta — as invariantes do domínio, direto na
 * entidade pura (sem banco, HTTP ou Kafka). É o retrato da Clean Architecture:
 * a regra de negócio testa-se sozinha, em milissegundos, e por isso cabe no
 * gate do pre-push.
 */

declare(strict_types=1);

use App\Domain\Proposta\Proposta;
use App\Domain\Proposta\StatusProposta;

final class PropostaTest extends MiniTestCase
{
    /** Reconstitui uma proposta no status desejado (como o Repository faz). */
    private function proposta(StatusProposta $status): Proposta
    {
        return Proposta::reconstituir(
            id: '11111111-1111-1111-1111-111111111111',
            editalId: '22222222-2222-2222-2222-222222222222',
            proponente: 'maria',
            titulo: 'IoT na agricultura familiar',
            descricao: 'Sensores de umidade de baixo custo.',
            retornoEsperado: 'Redução de 30% no consumo de água.',
            dataInicio: new DateTimeImmutable('2026-03-01'),
            mesesExecucao: 12,
            status: $status,
            submetidaEm: new DateTimeImmutable('2026-01-10'),
            parecer: null,
        );
    }

    public function testSubmeterRecusaPropostaSemTitulo(): void
    {
        self::assertLanca(DomainException::class, fn () => Proposta::submeter(
            editalId: 'e1', proponente: 'maria', titulo: '  ',
            descricao: 'desc', retornoEsperado: 'retorno',
            dataInicio: new DateTimeImmutable('2026-03-01'), mesesExecucao: 6,
        ));
    }

    public function testAvaliacaoAprovaERegistraParecer(): void
    {
        $proposta = $this->proposta(StatusProposta::Submetida);
        $proposta->registrarResultado(StatusProposta::Aprovada, 'Projeto sólido.');

        self::assertSame(StatusProposta::Aprovada, $proposta->status());
        self::assertSame('Projeto sólido.', $proposta->parecer());
    }

    public function testPropostaNaoPodeSerAvaliadaDuasVezes(): void
    {
        $proposta = $this->proposta(StatusProposta::Submetida);
        $proposta->registrarResultado(StatusProposta::Reprovada, 'Fora do escopo.');

        self::assertLanca(DomainException::class,
            fn () => $proposta->registrarResultado(StatusProposta::Aprovada, 'mudei de ideia'));
    }

    public function testSoPropostaAprovadaRecebePagamento(): void
    {
        $reprovada = $this->proposta(StatusProposta::Reprovada);
        self::assertLanca(DomainException::class, fn () => $reprovada->registrarPagamento(true));
    }

    public function testFalhaDePagamentoPermiteNovaTentativa(): void
    {
        $proposta = $this->proposta(StatusProposta::Aprovada);

        $proposta->registrarPagamento(false);                       // gateway falhou
        self::assertSame(StatusProposta::Aprovada, $proposta->status());
        self::assertNotNull($proposta->pagamentoFalhouEm());

        $proposta->registrarPagamento(true);                        // retry deu certo
        self::assertSame(StatusProposta::Paga, $proposta->status());
        self::assertNull($proposta->pagamentoFalhouEm());
        self::assertNotNull($proposta->pagoEm());
    }

    /*
    ─── Simulação de push recusado ──────────────────────────────────────
    Descomente o método abaixo e tente `git push` (em qualquer branch,
    inclusive main/master): o hook de pre-push roda `make test`, este
    teste falha e o git CANCELA o envio antes de sair da máquina.
    Comente de novo e o push volta a passar.
    */
    
    // public function testFalhaProposital_DescomenteParaBloquearOPush(): void
    // {
    //     self::assertSame(StatusProposta::Paga, $this->proposta(StatusProposta::Reprovada)->status(),
    //         'Falha proposital: demonstra o gate de testes recusando o push.');
    // }
}
