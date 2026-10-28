<?php

namespace App\Http\Controllers;

use App\Application\SubmeterProposta;
use App\Domain\Proposta\Proposta;
use App\Domain\Proposta\PropostaRepository;
use App\Domain\Proposta\StatusProposta;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Interface Adapter: traduz HTTP <-> Use Case. A identidade do proponente vem
 * SEMPRE do header X-Usuario, injetado pelo BFF a partir do JWT — nunca do
 * body nem da query string.
 */
class PropostaController extends Controller
{
    public function submeter(Request $request, SubmeterProposta $useCase): JsonResponse
    {
        $dados = $request->validate([
            'edital_id' => ['required', 'uuid'],
            'titulo' => ['required', 'string', 'max:200'],
            'descricao' => ['required', 'string'],
            'retorno_esperado' => ['required', 'string'],
            'data_inicio' => ['required', 'date'],
            'meses_execucao' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $proposta = $useCase->executar(
                editalId: $dados['edital_id'],
                proponente: (string) $request->header('X-Usuario', ''),
                titulo: $dados['titulo'],
                descricao: $dados['descricao'],
                retornoEsperado: $dados['retorno_esperado'],
                dataInicio: new DateTimeImmutable($dados['data_inicio']),
                mesesExecucao: (int) $dados['meses_execucao'],
            );
        } catch (DomainException $e) {
            return response()->json(['erro' => $e->getMessage()], 422);
        } catch (ConnectionException) {
            return response()->json(['erro' => 'Consulta de editais indisponível — tente novamente'], 503);
        }

        return response()->json($this->apresentar($proposta), 201);
    }

    public function listar(Request $request, PropostaRepository $propostas): JsonResponse
    {
        // Só o header conta: query string permitiria um proponente listar as
        // propostas dos outros.
        $proponente = (string) $request->header('X-Usuario', '');

        return response()->json(array_map(
            fn (Proposta $p) => $this->apresentar($p),
            $propostas->porProponente($proponente),
        ));
    }

    public function consultar(Request $request, string $id, PropostaRepository $propostas): JsonResponse
    {
        $proposta = $propostas->porId($id);

        // Proposta de outro proponente é indistinguível de inexistente (404,
        // não 403): não vaza nem a existência.
        if ($proposta === null || $proposta->proponente !== (string) $request->header('X-Usuario', '')) {
            return response()->json(['erro' => 'Proposta não encontrada'], 404);
        }

        return response()->json($this->apresentar($proposta));
    }

    /** Presenter simples: o aggregate nunca é serializado direto. */
    private function apresentar(Proposta $p): array
    {
        return [
            'id' => $p->id,
            'edital_id' => $p->editalId,
            'proponente' => $p->proponente,
            'titulo' => $p->titulo,
            'descricao' => $p->descricao,
            'retorno_esperado' => $p->retornoEsperado,
            'data_inicio' => $p->dataInicio->format('Y-m-d'),
            'meses_execucao' => $p->mesesExecucao,
            'status' => $p->status()->value,
            'parecer' => $p->parecer(),
            'aguardando_pagamento' => $p->status() === StatusProposta::Aprovada,
            'submetida_em' => $p->submetidaEm->format(DATE_ATOM),
        ];
    }
}
