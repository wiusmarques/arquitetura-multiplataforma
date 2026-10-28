<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EditalController;
use App\Models\Edital;
use Illuminate\Support\Facades\Route;

// Fluxo OIDC (públicas por natureza: é por aqui que se entra).
Route::get('/login', [AuthController::class, 'login']);
Route::get('/auth/callback', [AuthController::class, 'callback']);
Route::post('/logout', [AuthController::class, 'logout']);

// Painel da administração (Blade — monolito com UI server-side). Tudo atrás
// do login: o monolito é exclusivo da gestão interna.
Route::middleware('admin')->group(function () {
    Route::get('/', function () {
        return view('editais', ['editais' => Edital::orderBy('created_at')->get()]);
    });

    // Os forms do painel usam as MESMAS ações da API (um fluxo só a manter);
    // o controller responde redirect+flash para HTML e JSON para a API.
    Route::post('/editais', [EditalController::class, 'criar']);
    Route::get('/editais/{id}/propostas', [EditalController::class, 'propostas']);
    Route::post('/editais/{id}/publicar', [EditalController::class, 'publicar']);
    Route::post('/editais/{id}/excluir', [EditalController::class, 'excluir']);
});
