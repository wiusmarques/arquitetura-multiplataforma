<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validação de JWT no BFF: o CLIENTE nunca valida token — quem valida é o
 * gateway, com as chaves públicas (JWKS) do Keycloak.
 *
 * Checagens: assinatura RS256 (JWKS, com cache), issuer e expiração (o
 * firebase/php-jwt rejeita `exp` vencido sozinho). Sem token → 401; token
 * válido sem o papel exigido → 403.
 */
class AutenticaJwt
{
    public function handle(Request $request, Closure $next, ?string $roleExigida = null): Response
    {
        $token = $request->bearerToken();
        if ($token === null) {
            return response()->json(['erro' => 'Token ausente'], 401);
        }

        try {
            $claims = JWT::decode($token, JWK::parseKeySet($this->jwks()));
        } catch (\Throwable $e) {
            return response()->json(['erro' => 'Token inválido: '.$e->getMessage()], 401);
        }

        $issuer = config('services.keycloak.issuer');
        if (($claims->iss ?? '') !== $issuer) {
            return response()->json(['erro' => 'Issuer inesperado'], 401);
        }

        $roles = $claims->realm_access->roles ?? [];
        if ($roleExigida !== null && ! in_array($roleExigida, $roles, true)) {
            return response()->json(['erro' => "Acesso requer o papel '{$roleExigida}'"], 403);
        }

        // Identidade propagada aos serviços internos via header — eles confiam
        // no BFF (rede interna), nunca no cliente.
        $request->attributes->set('usuario', $claims->preferred_username ?? ($claims->sub ?? ''));
        $request->attributes->set('roles', $roles);

        return $next($request);
    }

    private function jwks(): array
    {
        return Cache::remember('keycloak.jwks', 300, function () {
            $issuer = config('services.keycloak.issuer');

            return Http::timeout(5)
                ->get($issuer.'/protocol/openid-connect/certs')
                ->throw()
                ->json();
        });
    }
}
