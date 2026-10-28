<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;

/**
 * BFF: único ponto de entrada de web/mobile. Roteia para os serviços
 * internos, injetando a identidade validada (X-Usuario). Indisponibilidade de
 * um serviço vira 503 para o cliente — o circuit breaker entrará nesta borda.
 */
class GatewayController extends Controller
{
    // ---- Editais (público — query side) ------------------------------------

    public function listarEditais(Request $request): JsonResponse
    {
        return $this->repassar(fn () => Http::timeout(5)
            ->get(config('services.edital.base').'/v1/editais', $request->query()));
    }

    public function consultarEdital(string $id): JsonResponse
    {
        return $this->repassar(fn () => Http::timeout(5)
            ->get(config('services.edital.base')."/v1/editais/{$id}"));
    }

    // ---- Transparência (público — órgãos de controle / dados abertos) ------

    public function transparenciaRecursos(): JsonResponse
    {
        return $this->repassar(fn () => Http::timeout(10)
            ->get(config('services.edital.base').'/v1/transparencia/recursos'));
    }

    public function prestacaoContas(string $editalId): JsonResponse
    {
        return $this->repassar(fn () => Http::timeout(10)
            ->get(config('services.edital.base')."/v1/transparencia/prestacao-contas/{$editalId}"));
    }

    // ---- Propostas (role proponente) ----------------------------------------

    public function submeterProposta(Request $request): JsonResponse
    {
        return $this->repassar(fn () => Http::timeout(10)
            ->withHeaders(['X-Usuario' => $request->attributes->get('usuario')])
            ->post(config('services.proposta.base').'/v1/propostas', $request->all()));
    }

    public function minhasPropostas(Request $request): JsonResponse
    {
        return $this->repassar(fn () => Http::timeout(5)
            ->withHeaders(['X-Usuario' => $request->attributes->get('usuario')])
            ->get(config('services.proposta.base').'/v1/propostas'));
    }

    public function consultarProposta(Request $request, string $id): JsonResponse
    {
        return $this->repassar(fn () => Http::timeout(5)
            ->withHeaders(['X-Usuario' => $request->attributes->get('usuario')])
            ->get(config('services.proposta.base')."/v1/propostas/{$id}"));
    }

    // ---- Avaliação (role avaliador) -----------------------------------------

    public function minhasTarefas(Request $request): JsonResponse
    {
        return $this->repassar(fn () => Http::timeout(5)
            ->get(config('services.avaliacao.base').'/v1/tarefas', [
                'avaliador' => $request->attributes->get('usuario'),
            ]));
    }

    public function registrarParecer(Request $request, string $tarefaId): JsonResponse
    {
        return $this->repassar(fn () => Http::timeout(10)
            ->withHeaders(['X-Usuario' => $request->attributes->get('usuario')])
            ->post(config('services.avaliacao.base')."/v1/tarefas/{$tarefaId}/parecer", $request->all()));
    }

    // -------------------------------------------------------------------------

    /** Repassa status e corpo do serviço interno; rede fora do ar vira 503. */
    private function repassar(callable $chamada): JsonResponse
    {
        try {
            /** @var ClientResponse $resposta */
            $resposta = $chamada();
        } catch (ConnectionException) {
            return response()->json(['erro' => 'Serviço temporariamente indisponível'], 503);
        }

        return response()->json($resposta->json(), $resposta->status());
    }
}
