<?php

namespace App\Http\Middleware;

use App\Services\ValidadorToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Porteiro do monolito: só a role `admin` entra. Aceita duas credenciais:
 * - sessão Laravel (painel Blade, criada pelo callback OIDC);
 * - bearer JWT (API /v1, validado via JWKS como no BFF).
 * Sem credencial: navegador vai para /login, API recebe 401.
 */
class AutenticaAdmin
{
    public function __construct(private readonly ValidadorToken $validador)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Painel web: identidade na sessão (rotas `web` têm sessão; a API não).
        if ($request->hasSession() && $request->session()->has('usuario')) {
            if (in_array('admin', $request->session()->get('roles', []), true)) {
                return $next($request);
            }
            abort(403, 'Acesso restrito à administração.');
        }

        // API: bearer token, mesma validação JWKS do BFF.
        $token = $request->bearerToken();
        if ($token !== null) {
            try {
                $claims = $this->validador->claims($token);
            } catch (\Throwable $e) {
                return response()->json(['erro' => 'Token inválido: '.$e->getMessage()], 401);
            }

            if (! in_array('admin', $this->validador->roles($claims), true)) {
                return response()->json(['erro' => "Acesso requer o papel 'admin'"], 403);
            }

            $request->attributes->set('usuario', $claims->preferred_username ?? $claims->sub);

            return $next($request);
        }

        // API é sempre JSON (mesmo sem Accept); navegador vai para o login.
        return $request->is('v1/*') || $request->expectsJson()
            ? response()->json(['erro' => 'Autenticação necessária'], 401)
            : redirect('/login');
    }
}
