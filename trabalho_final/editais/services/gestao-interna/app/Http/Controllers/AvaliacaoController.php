<?php

namespace App\Http\Controllers;

use App\Models\Edital;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

    public function atribuirAvaliador(Request $request, string $tarefaId): JsonResponse|RedirectResponse
    {
        $dados = $request->validate(['avaliador' => ['required', 'string']]);

        $resposta = Http::timeout(5)
            ->put(config('services.avaliacao.base')."/v1/tarefas/{$tarefaId}/avaliador", $dados);

        if ($request->expectsJson()) {
            return response()->json($resposta->json(), $resposta->status());
        }

        return $resposta->successful()
            ? redirect()->back()->with('ok', "Tarefa atribuída a {$dados['avaliador']}.")
            : redirect()->back()->with('erro', $resposta->json('detail') ?? 'Falha ao atribuir avaliador');
    }

    /** Página do admin: tarefas de avaliação de um edital, com atribuição. */
    public function painel(string $editalId, \App\Services\KeycloakAvaliadores $avaliadores)
    {
        $edital = Edital::findOrFail($editalId);

        $tarefas = Http::timeout(5)
            ->get(config('services.avaliacao.base').'/v1/tarefas', ['edital_id' => $edital->id])
            ->throw()
            ->json();

        return view('avaliacao', [
            'edital' => $edital,
            'tarefas' => $tarefas,
            'avaliadores' => $avaliadores->listar(),
        ]);
    }
}
