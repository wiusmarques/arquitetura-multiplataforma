<?php

namespace App\Application\Ports;

/**
 * Port do gateway de pagamento: o use case pede "pague este apoio" sem saber
 * quem executa. No laboratório o adapter chama o stub ilustrativo do
 * gestao-interna; na vida real seria o gateway financeiro do órgão.
 */
interface Pagamentos
{
    /** @return bool true quando o pagamento foi efetuado */
    public function executar(string $propostaId, string $proponente, float $valor): bool;
}
