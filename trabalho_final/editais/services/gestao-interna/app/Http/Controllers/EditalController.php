<?php

namespace App\Http\Controllers;

use App\Models\Edital;
use App\Models\Outbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Command side dos editais (CQRS): o monolito só
 * ESCREVE; quem serve a leitura pública é o edital-service (Python), populado
 * pelos eventos que saem daqui via Outbox.
 */
class EditalController extends Controller
{
    public function listar(): JsonResponse
    {
        return response()->json(Edital::orderBy('created_at')->get());
    }

    public function criar(Request $request): JsonResponse|RedirectResponse
    {
        $edital = Edital::create($this->validar($request) + ['status' => 'rascunho']);

        return $request->expectsJson()
            ? response()->json($edital, 201)
            : redirect('/')->with('ok', "Edital \"{$edital->titulo}\" criado como rascunho.");
    }

    /** Edital publicado é imutável — só rascunho pode ser editado. */
    public function atualizar(Request $request, string $id): JsonResponse
    {
        $edital = Edital::findOrFail($id);
        if ($edital->status !== 'rascunho') {
            return response()->json(['erro' => "Só rascunho pode ser editado (está '{$edital->status}')"], 409);
        }

        $edital->update($this->validar($request));

        return response()->json($edital->refresh());
    }

    public function excluir(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $edital = Edital::findOrFail($id);
        if ($edital->status !== 'rascunho') {
            $erro = "Só rascunho pode ser excluído (está '{$edital->status}')";

            return $request->expectsJson()
                ? response()->json(['erro' => $erro], 409)
                : redirect('/')->with('erro', $erro);
        }

        $edital->delete();

        return $request->expectsJson()
            ? response()->json(null, 204)
            : redirect('/')->with('ok', "Rascunho \"{$edital->titulo}\" excluído.");
    }

    /** @return array<string, mixed> dados validados */
    private function validar(Request $request): array
    {
        $dados = $request->validate([
            'titulo' => ['required', 'string', 'max:200'],
            'descricao' => ['nullable', 'string'],
            'valor_total' => ['required', 'numeric', 'gt:0'],
            'valor_por_candidato' => ['required', 'numeric', 'gt:0'],
            'numero_candidatos' => ['required', 'integer', 'min:1'],
            'nota_maxima' => ['required', 'numeric', 'gt:0', 'max:999.99'],
            'data_abertura' => ['required', 'date'],
            'data_encerramento' => ['required', 'date', 'after:data_abertura'],
        ]);

        // Coerência orçamentária: o teto por candidato vezes o nº de vagas não
        // pode ultrapassar o valor total do edital.
        if ($dados['valor_por_candidato'] * $dados['numero_candidatos'] > (float) $dados['valor_total']) {
            throw ValidationException::withMessages([
                'valor_por_candidato' => 'valor_por_candidato × numero_candidatos excede o valor_total do edital.',
            ]);
        }

        return $dados;
    }

    /**
     * Outbox Pattern: mudar o status e registrar o evento acontecem na MESMA
     * transação; o relay publica edital.publicado no Kafka depois.
     */
    public function publicar(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $edital = Edital::findOrFail($id);
        if ($edital->status !== 'rascunho') {
            $erro = "Edital já está '{$edital->status}'";

            return $request->expectsJson()
                ? response()->json(['erro' => $erro], 409)
                : redirect('/')->with('erro', $erro);
        }

        DB::transaction(function () use ($edital) {
            $edital->update(['status' => 'publicado']);
            Outbox::create([
                'topico' => 'edital.publicado',
                'chave' => $edital->id,
                'payload' => [
                    'edital_id' => $edital->id,
                    'titulo' => $edital->titulo,
                    'descricao' => $edital->descricao,
                    'valor_total' => (float) $edital->valor_total,
                    'valor_por_candidato' => (float) $edital->valor_por_candidato,
                    'numero_candidatos' => $edital->numero_candidatos,
                    'nota_maxima' => (float) $edital->nota_maxima,
                    'data_abertura' => $edital->data_abertura->format('Y-m-d'),
                    'data_encerramento' => $edital->data_encerramento->format('Y-m-d'),
                    'publicado_em' => now()->toIso8601String(),
                ],
                'criado_em' => now(),
            ]);
        });

        return $request->expectsJson()
            ? response()->json($edital->refresh())
            : redirect('/')->with('ok', "Edital \"{$edital->titulo}\" publicado — evento na fila do outbox.");
    }

    /**
     * Acompanhamento da administração: as propostas moram no proposta-service
     * (dono do dado); o monolito consulta por REST e só apresenta.
     */
    public function propostas(Request $request, string $id)
    {
        $edital = Edital::findOrFail($id);

        $propostas = Http::timeout(5)
            ->get(config('services.proposta.base')."/v1/editais/{$edital->id}/propostas")
            ->throw()
            ->json();

        if ($request->expectsJson()) {
            return response()->json(['edital' => $edital, 'propostas' => $propostas]);
        }

        return view('propostas', ['edital' => $edital, 'propostas' => $propostas]);
    }

    /**
     * Fecha o edital e publica o resultado: o resumo vem do avaliacao-service
     * (REST síncrono, admin -> avaliação) e o evento resultado.publicado sai
     * pela outbox para o read model e as notificações.
     */
    public function publicarResultado(string $id): JsonResponse
    {
        $edital = Edital::findOrFail($id);
        if ($edital->status !== 'publicado') {
            return response()->json(['erro' => "Edital precisa estar 'publicado' (está '{$edital->status}')"], 409);
        }

        $tarefas = Http::timeout(5)
            ->get(config('services.avaliacao.base').'/v1/tarefas', ['edital_id' => $edital->id])
            ->throw()
            ->json();

        $pendentes = array_filter($tarefas, fn ($t) => $t['status'] !== 'concluida');
        if ($pendentes !== []) {
            return response()->json([
                'erro' => count($pendentes).' tarefa(s) de avaliação ainda pendente(s)',
            ], 409);
        }

        $aprovadas = array_values(array_filter($tarefas, fn ($t) => $t['resultado'] === 'aprovada'));
        $resumo = [
            'propostas_avaliadas' => count($tarefas),
            'aprovadas' => count($aprovadas),
            'reprovadas' => count($tarefas) - count($aprovadas),
            'valor_aprovado' => array_sum(array_map(fn ($t) => (float) $t['valor_solicitado'], $aprovadas)),
        ];

        DB::transaction(function () use ($edital, $resumo) {
            $edital->update(['status' => 'resultado_publicado']);
            Outbox::create([
                'topico' => 'resultado.publicado',
                'chave' => $edital->id,
                'payload' => [
                    'edital_id' => $edital->id,
                    'resumo' => $resumo,
                    'publicado_em' => now()->toIso8601String(),
                ],
                'criado_em' => now(),
            ]);
        });

        return response()->json(['edital' => $edital->refresh(), 'resumo' => $resumo]);
    }
}
