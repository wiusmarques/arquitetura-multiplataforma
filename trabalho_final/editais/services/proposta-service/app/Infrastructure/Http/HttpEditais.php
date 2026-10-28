<?php

namespace App\Infrastructure\Http;

use App\Application\Ports\Editais;
use Illuminate\Support\Facades\Http;

/**
 * Adapter do port Editais: consulta o read model público do edital-service
 * via REST. Timeout curto — essa checagem é pré-condição do request do
 * usuário e não pode segurá-lo; indisponibilidade estoura ConnectionException
 * (o controller traduz em 503).
 */
class HttpEditais implements Editais
{
    public function porId(string $editalId): ?array
    {
        $resposta = Http::timeout(3)
            ->get(config('services.edital.base')."/v1/editais/{$editalId}");

        if ($resposta->status() === 404) {
            return null;
        }

        $edital = $resposta->throw()->json();

        return [
            'status' => (string) ($edital['status'] ?? ''),
            'data_encerramento' => $edital['data_encerramento'] ?? null,
        ];
    }
}
