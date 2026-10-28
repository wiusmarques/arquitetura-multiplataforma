<?php

namespace App\Application\Ports;

/**
 * Port de consulta a editais: o use case precisa saber se o edital aceita
 * propostas, mas o domínio não conhece HTTP — quem fala com o edital-service
 * é o adapter na Infrastructure.
 */
interface Editais
{
    /**
     * @return array{status: string, data_encerramento: ?string}|null
     *         null quando o edital não existe
     */
    public function porId(string $editalId): ?array;
}
