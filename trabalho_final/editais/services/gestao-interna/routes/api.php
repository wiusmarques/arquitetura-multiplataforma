<?php

use App\Http\Controllers\AvaliacaoController;
use App\Http\Controllers\EditalController;
use Illuminate\Support\Facades\Route;

// API interna da administração. O monolito é exclusivo da rede interna — não
// passa pelo BFF nem é exposto a proponente/cidadão/avaliador. Toda a API
// exige bearer JWT com a role `admin` (validação JWKS no middleware).
Route::middleware('admin')->group(function () {
    Route::get('/editais', [EditalController::class, 'listar']);
    Route::get('/editais/{id}/propostas', [EditalController::class, 'propostas']);
    Route::post('/editais', [EditalController::class, 'criar']);
    Route::put('/editais/{id}', [EditalController::class, 'atualizar']);
    Route::delete('/editais/{id}', [EditalController::class, 'excluir']);
    Route::post('/editais/{id}/publicar', [EditalController::class, 'publicar']);
    Route::post('/editais/{id}/resultado', [EditalController::class, 'publicarResultado']);

    Route::get('/avaliacao/tarefas', [AvaliacaoController::class, 'tarefas']);
    Route::put('/avaliacao/tarefas/{tarefaId}/avaliador', [AvaliacaoController::class, 'atribuirAvaliador']);
});
