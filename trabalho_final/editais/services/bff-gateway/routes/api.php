<?php

use App\Http\Controllers\GatewayController;
use Illuminate\Support\Facades\Route;

// Rotas públicas: consulta de editais (maior volumetria do sistema — qualquer
// cidadão, sem login) e API de transparência (órgãos de controle/dados abertos).
Route::get('/editais', [GatewayController::class, 'listarEditais']);
Route::get('/editais/{id}', [GatewayController::class, 'consultarEdital']);
Route::get('/transparencia/recursos', [GatewayController::class, 'transparenciaRecursos']);
Route::get('/transparencia/prestacao-contas/{editalId}', [GatewayController::class, 'prestacaoContas']);

// Área do proponente (JWT com papel `proponente`).
Route::middleware('jwt:proponente')->group(function () {
    Route::post('/propostas', [GatewayController::class, 'submeterProposta']);
    Route::get('/propostas', [GatewayController::class, 'minhasPropostas']);
    Route::get('/propostas/{id}', [GatewayController::class, 'consultarProposta']);
});

// Área do avaliador (JWT com papel `avaliador`) — avaliador é EXTERNO,
// acessa pelo app web via BFF; o monolito fica só com a administração.
Route::middleware('jwt:avaliador')->group(function () {
    Route::get('/avaliacao/tarefas', [GatewayController::class, 'minhasTarefas']);
    Route::post('/avaliacao/tarefas/{tarefaId}/parecer', [GatewayController::class, 'registrarParecer']);
});
