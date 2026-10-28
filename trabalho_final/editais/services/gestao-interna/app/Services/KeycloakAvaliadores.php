<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Lista os avaliadores a partir do grupo /avaliadores do Keycloak (Admin API):
 * identidade mora no IdP, então a lista vem de lá — nada de tabela paralela.
 * O acesso usa o service account do próprio client gestao-interna
 * (client_credentials + role view-users de realm-management).
 *
 * Falha do Keycloak não derruba o painel: devolve lista vazia e a view cai
 * no campo de texto livre.
 */
class KeycloakAvaliadores
{
    /** @return string[] usernames do grupo /avaliadores */
    public function listar(): array
    {
        return Cache::remember('keycloak.avaliadores', 300, function () {
            try {
                $kc = config('services.keycloak');
                // issuer = {base}/realms/{realm} -> Admin API = {base}/admin/realms/{realm}
                $admin = str_replace('/realms/', '/admin/realms/', $kc['issuer']);

                $token = Http::asForm()->timeout(5)
                    ->post($kc['issuer'].'/protocol/openid-connect/token', [
                        'grant_type' => 'client_credentials',
                        'client_id' => $kc['client_id'],
                        'client_secret' => $kc['client_secret'],
                    ])->throw()->json('access_token');

                $grupos = Http::withToken($token)->timeout(5)
                    ->get($admin.'/groups', ['search' => 'avaliadores'])
                    ->throw()->json();
                $grupo = collect($grupos)->firstWhere('name', 'avaliadores');
                if ($grupo === null) {
                    return [];
                }

                $membros = Http::withToken($token)->timeout(5)
                    ->get($admin."/groups/{$grupo['id']}/members")
                    ->throw()->json();

                return collect($membros)->pluck('username')->sort()->values()->all();
            } catch (\Throwable $e) {
                Log::warning('Não foi possível listar avaliadores no Keycloak: '.$e->getMessage());

                return [];
            }
        });
    }
}
