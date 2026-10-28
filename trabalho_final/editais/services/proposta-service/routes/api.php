<?php

use App\Http\Controllers\PropostaController;
use Illuminate\Support\Facades\Route;

// Interface Adapters (Clean Architecture): o controller só traduz HTTP para
// chamadas de Use Case — a regra de negócio mora em app/Domain e app/Application.
//
// Este serviço fica atrás do BFF, que já validou o JWT e injeta a identidade
// do proponente no header X-Usuario.
Route::post('/propostas', [PropostaController::class, 'submeter']);
Route::get('/propostas', [PropostaController::class, 'listar']);
Route::get('/propostas/{id}', [PropostaController::class, 'consultar']);
