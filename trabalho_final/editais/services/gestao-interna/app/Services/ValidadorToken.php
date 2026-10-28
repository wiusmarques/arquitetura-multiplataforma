<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Validação de tokens do Keycloak (mesmo padrão do AutenticaJwt do BFF):
 * assinatura RS256 via JWKS com cache, issuer e expiração (o firebase/php-jwt
 * rejeita `exp` vencido sozinho). Usado no callback do login OIDC e no
 * middleware da API.
 */
class ValidadorToken
{
    /** @throws \Throwable token inválido, expirado ou de outro issuer */
    public function claims(string $token): object
    {
        $claims = JWT::decode($token, JWK::parseKeySet($this->jwks()));

        $issuer = config('services.keycloak.issuer');
        if (($claims->iss ?? '') !== $issuer) {
            throw new \RuntimeException('Issuer inesperado');
        }

        return $claims;
    }

    /** @return string[] roles de realm do token */
    public function roles(object $claims): array
    {
        return (array) ($claims->realm_access->roles ?? []);
    }

    private function jwks(): array
    {
        return Cache::remember('keycloak.jwks', 300, function () {
            return Http::timeout(5)
                ->get(config('services.keycloak.issuer').'/protocol/openid-connect/certs')
                ->throw()
                ->json();
        });
    }
}
