<?php

namespace App\Domain\Proposta;

enum StatusProposta: string
{
    case Submetida = 'submetida';
    case Aprovada = 'aprovada';
    case Reprovada = 'reprovada';
    // Pós-aprovação: definido pelo fluxo de pagamentos (consumer de
    // resultado.publicado), ainda não implementado.
    case Paga = 'paga';
}
