<?php

namespace App\Http\Controllers;

use App\Services\ValidadorToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Login OIDC do admin: Authorization Code com client
 * CONFIDENCIAL — o navegador só vê a tela de login do Keycloak; quem troca o
 * code pelo token é o servidor, usando o client_secret. Nenhuma senha passa
 * pelo monolito, e a sessão Laravel guarda a identidade depois do callback.
 */
class AuthController extends Controller
{
    public function login(Request $request): RedirectResponse
    {
        // `state` anti-CSRF do OIDC: gerado aqui, conferido no callback.
        $state = Str::random(40);
        $request->session()->put('oidc_state', $state);

        $kc = config('services.keycloak');

        $params = [
            'client_id' => $kc['client_id'],
            'redirect_uri' => $kc['public_url'].'/auth/callback',
            'response_type' => 'code',
            'scope' => 'openid',
            'state' => $state,
        ];
        // "Entrar com outra conta": sem isso o SSO do Keycloak devolve a
        // sessão atual sem mostrar a tela de login (loop para quem caiu no
        // acesso negado). prompt=login força reautenticação.
        if ($request->query('prompt') === 'login') {
            $params['prompt'] = 'login';
        }

        return redirect($kc['issuer'].'/protocol/openid-connect/auth?'.http_build_query($params));
    }

    public function callback(Request $request, ValidadorToken $validador)
    {
        if ($request->query('state') !== $request->session()->pull('oidc_state')) {
            abort(403, 'state do OIDC não confere — refaça o login.');
        }
        if ($request->query('code') === null) {
            abort(400, 'Keycloak não devolveu o code: '.$request->query('error_description', 'erro desconhecido'));
        }

        $kc = config('services.keycloak');

        $tokens = Http::asForm()->timeout(5)
            ->post($kc['issuer'].'/protocol/openid-connect/token', [
                'grant_type' => 'authorization_code',
                'code' => $request->query('code'),
                'redirect_uri' => $kc['public_url'].'/auth/callback',
                'client_id' => $kc['client_id'],
                'client_secret' => $kc['client_secret'],
            ])
            ->throw()
            ->json();

        // Mesmo vindo direto do Keycloak, o token é validado (assinatura,
        // issuer, expiração) — nenhum atalho de confiança.
        $claims = $validador->claims($tokens['access_token']);

        if (! in_array('admin', $validador->roles($claims), true)) {
            return response()->view('acesso-negado', [
                'usuario' => $claims->preferred_username ?? '?',
            ], 403);
        }

        $request->session()->regenerate(); // evita fixação de sessão
        $request->session()->put([
            'usuario' => $claims->preferred_username ?? $claims->sub,
            'roles' => $validador->roles($claims),
            'id_token' => $tokens['id_token'] ?? null, // para o logout no Keycloak
        ]);

        return redirect('/');
    }

    public function logout(Request $request): RedirectResponse
    {
        $idToken = $request->session()->get('id_token');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $kc = config('services.keycloak');

        // Encerra também a sessão NO KEYCLOAK — senão o próximo "login" entra
        // sozinho, sem pedir senha (single sign-on ainda ativo).
        return redirect($kc['issuer'].'/protocol/openid-connect/logout?'.http_build_query(array_filter([
            'id_token_hint' => $idToken,
            'post_logout_redirect_uri' => $kc['public_url'].'/',
            'client_id' => $kc['client_id'],
        ])));
    }
}
