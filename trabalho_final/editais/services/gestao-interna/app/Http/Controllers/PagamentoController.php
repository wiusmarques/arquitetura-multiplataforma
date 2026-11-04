<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PagamentoController extends Controller
{
    /**
     * STUB ILUSTRATIVO do gateway de pagamento (na vida real, a integração
     * financeira do órgão — ex.: SIAFI/OB). Recebe a ordem e responde se foi
     * efetuada — aleatoriamente, só para exercitar o fluxo: o sucesso fecha o
     * ciclo e a falha exercita a ressolicitação. Nenhum dinheiro existe aqui.
     */
    public function executar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'proposta_id' => ['required', 'uuid'],
            'proponente' => ['required', 'string'],
            'valor' => ['required', 'numeric', 'gt:0'],
        ]);

        $efetuado = random_int(0, 1) === 1;

        Log::info(sprintf(
            'pagamento %s: proposta %s, proponente %s, R$ %.2f',
            $efetuado ? 'EFETUADO' : 'RECUSADO',
            $dados['proposta_id'], $dados['proponente'], $dados['valor'],
        ));

        return response()->json([
            'efetuado' => $efetuado,
            'proposta_id' => $dados['proposta_id'],
            'valor' => (float) $dados['valor'],
            'processado_em' => now()->toIso8601String(),
        ]);
    }

    /**
     * Ressolicitação pelo painel: o admin pede nova tentativa para uma
     * proposta aprovada cujo pagamento falhou. Quem reexecuta é o
     * proposta-service (dono do estado da proposta).
     */
    public function solicitar(string $propostaId): RedirectResponse
    {
        $resposta = Http::timeout(10)
            ->post(config('services.proposta.base')."/v1/propostas/{$propostaId}/pagar");

        if (! $resposta->successful()) {
            return redirect()->back()->with('erro', $resposta->json('erro') ?? 'Falha ao solicitar o pagamento');
        }

        return $resposta->json('status') === 'paga'
            ? redirect()->back()->with('ok', 'Pagamento efetuado.')
            : redirect()->back()->with('erro', 'O gateway recusou novamente — solicite outra vez.');
    }
}
