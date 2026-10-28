<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;

/**
 * O admin acompanha e distribui as avaliações daqui, mas quem é dono do
 * domínio é o avaliacao-service — o monolito só conversa por REST.
 */
class AvaliacaoController extends Controller
{
    public function tarefas(Request $request): JsonResponse
    {
        $resposta = Http::timeout(5)
            ->get(config('services.avaliacao.base').'/v1/tarefas', $request->query());

        return response()->json($resposta->json(), $resposta->status());
    }

    public function atribuirAvaliador(Request $request, string $tarefaId): JsonResponse
    {
        $dados = $request->validate(['avaliador' => ['required', 'string']]);

        $resposta = Http::timeout(5)
            ->put(config('services.avaliacao.base')."/v1/tarefas/{$tarefaId}/avaliador", $dados);

        return response()->json($resposta->json(), $resposta->status());
    }
}
