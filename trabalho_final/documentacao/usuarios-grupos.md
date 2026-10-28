# Usuários e Grupos — Keycloak (Gov.br simulado)

## Contexto

| Tipo de usuário | Como se autentica |
|---|---|
| Administrador, Avaliador, Proponente (PF/PJ) | Login humano via OIDC (Authorization Code + PKCE no app-web) |
| Sistemas externos (órgãos de controle, dados abertos) | **`client_credentials`** (OAuth2 máquina-a-máquina, sem usuário humano) |
| Cidadão | **Não se autentica** — consulta pública é rota aberta no query side |

## Grupos, roles e usuários

Grupos planos; a role vem do **grupo**, não do usuário. Senha de todos os usuários humanos: **`Puc@2026!`** (credencial de laboratório, de propósito — em produção iria para secret manager e o usuário final logaria no Gov.br real).

| Grupo | Role | Usuários |
|---|---|---|
| `/administradores` | `admin` | `maria.admin` |
| `/avaliadores` | `avaliador` | `ana.avaliadora` |
| `/proponentes` | `proponente` | `joao.proponente` (PF), `empresa.acme` (PJ) |
| `/sistemas-externos` | `sistema-externo` | `service-account-orgao-controle` (service account do client `orgao-controle`) |

A role `sistema-externo` é **independente** das demais: o acesso amplo dos sistemas externos à API é regra de autorização nos serviços.

Os JWTs carregam a claim **`groups`** (caminho completo, ex.: `/proponentes`) além de `realm_access.roles` — dá para validar tanto por role quanto por grupo.

## Clients

| Client | Tipo | Uso |
|---|---|---|
| `app-web` | Público, PKCE S256 | SPA — login dos usuários humanos |
| `orgao-controle` | Confidencial, service account | Sistema externo M2M — secret `orgao-controle-secret` |

## URLs e execuções úteis

- Console admin: http://idp.editais.local:8089 (`admin` / `admin`) — realm `editais`.
- Subir o stack: `make up` · Reimportar o realm após editar `docker/keycloak/editais-realm.json`: `make kc-reset` (recria o database `keycloak` no Postgres e reimporta; o database `editais` do domínio e o Kafka ficam intactos).

Token de usuário: pelo **app-web** (http://app.editais.local:5173) — o login redireciona ao Keycloak (Authorization Code + PKCE) e o SPA recebe o JWT.

Token de sistema externo (client_credentials):

```sh
curl -s -X POST http://idp.editais.local:8089/realms/editais/protocol/openid-connect/token \
  -d grant_type=client_credentials \
  -d client_id=orgao-controle -d client_secret=orgao-controle-secret
```

Decodificar o payload de um JWT (ver roles e grupos):

```sh
echo $TOKEN | cut -d. -f2 | base64 -d 2>/dev/null | python3 -m json.tool
```
