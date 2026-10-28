# Plataforma de Editais Federais

Monorepo do trabalho final de **Arquitetura de Software Multiplataforma** (PUC Minas). Plataforma para gestão de editais federais de fomento: a administração publica editais, pessoas físicas e jurídicas submetem propostas solicitando recursos, avaliadores externos emitem pareceres e os resultados são públicos, com API de transparência para órgãos de controle.

Arquitetura, diagramas C4 e justificativas em [`../documentacao/`](../documentacao/) (`decisoes-arquiteturais.md` e `diagramas/c4-editais.drawio`).

## Serviços, URLs e portas

Cada serviço tem hostname e porta próprios (ver [Configurar os hosts](#configurar-os-hosts-da-máquina)); quase tudo responde também em `http://localhost:<porta>`.

### Aplicação

| Serviço | Plataforma | URL |
|---|---|---|
| app-web (SPA) | JavaScript | http://app.editais.local:5173 |
| bff-gateway | PHP 8.3 / Laravel | http://bff.editais.local:8000 |
| edital-service (query side) | Python 3.12 / FastAPI | http://edital.editais.local:8001 |
| proposta-service | PHP 8.3 / Laravel | http://proposta.editais.local:8002 |
| avaliacao-service | Python 3.12 / FastAPI | http://avaliacao.editais.local:8003 |
| notification-service | Python 3.12 / FastAPI | http://notificacao.editais.local:8004 |
| gestao-interna (monolito) | PHP 8.3 / Laravel | http://gestao.editais.local:8080 |

### Infraestrutura

| Serviço | Papel | URL / endpoint |
|---|---|---|
| Keycloak | Identity Provider (simula o Gov.br) | http://idp.editais.local:8089 (admin: `admin`/`admin`) |
| Mailpit | Caixa de e-mail do laboratório | http://mail.editais.local:8025 (SMTP interno: `mailpit:1025`) |
| Kafka UI | Inspeção de tópicos e mensagens | http://kafka.editais.local:8090 |
| Kafka (broker) | Eventos de domínio | `kafka:9092` (containers) · `localhost:29092` (host) |
| PostgreSQL 16 | Um schema por serviço | `postgres:5432` (containers) · `localhost:5432` (host) |
| Grafana | Observabilidade | http://grafana.editais.local:3333 *(porta reservada)* |
| Prometheus | Métricas | `localhost:9090` *(porta reservada)* |

> O Keycloak escuta na **mesma porta (8089) dentro e fora** do compose, e o container tem o alias de rede `idp.editais.local`. Assim a URL do issuer dos JWTs é idêntica para o navegador e para os serviços — sem divergência entre quem emite e quem valida o token.

## Configurar os hosts da máquina

Acrescente **uma linha** ao arquivo de hosts, com todos os hostnames do ambiente (o alvo `make hosts` a imprime pronta):

```
127.0.0.1 app.editais.local gestao.editais.local bff.editais.local edital.editais.local proposta.editais.local avaliacao.editais.local notificacao.editais.local idp.editais.local mail.editais.local kafka.editais.local grafana.editais.local
```

Obrigatórios de verdade são só os dois do fluxo OIDC — `idp.editais.local` (issuer dos JWTs: navegador e serviços precisam enxergar a mesma URL) e `gestao.editais.local` (redirect do login do painel de gestão). Os demais são conveniência: tudo também responde em `http://localhost:<porta>`.

- **Linux / macOS**: `sudo nano /etc/hosts`
- **Windows**: abrir o Bloco de Notas **como administrador** e editar `C:\Windows\System32\drivers\etc\hosts`
- **WSL2**: editar o hosts do **Windows** (acima); o `/etc/hosts` do WSL é regenerado a partir dele.

## Subindo o ambiente

Pré-requisitos: Docker + Docker Compose v2 e `make`.

```bash
make up         # garante o .env e sobe a infraestrutura
make topics-up  # cria os tópicos Kafka do domínio
make ps         # confere os healthchecks
```

`make help` lista todos os alvos (`logs`, `psql`, `hosts`, `down`, `reset`…).

## Sobre o `.env` — leia antes de estranhar

O `.env.example` deste repositório é versionado **com credenciais preenchidas**, e `make up` o copia para `.env` automaticamente. Isso é uma escolha deliberada **do laboratório**: o objetivo é simular o ambiente real com facilidade — qualquer avaliador clona o repositório numa máquina limpa e sobe o sistema inteiro com um comando, sem depender de segredo distribuído por fora.

**Na vida real, essas informações jamais subiriam para o repositório.** Senhas de banco, admin do Keycloak e client secrets ficariam em um **secret manager** (Vault, AWS Secrets Manager, GCP Secret Manager) ou em **variáveis protegidas do CI/CD**, injetadas nos containers em tempo de deploy. O `.env.example` real teria apenas as chaves, sem valores, e o `.env` continuaria fora do versionamento (como já está no `.gitignore`).

## Tópicos Kafka

| Tópico | Produtor | Consumidores |
|---|---|---|
| `edital.publicado` | gestao-interna (Outbox) | edital-service, notification-service |
| `proposta.submetida` | proposta-service (Outbox) | avaliacao-service, notification-service |
| `proposta.avaliada` | avaliacao-service | proposta-service, notification-service |
| `resultado.publicado` | gestao-interna (Outbox) | edital-service, notification-service |

O auto-create de tópicos está **desligado** no broker: partições e retenção são decisão de arquitetura, não efeito colateral do primeiro producer. `make topics-up` cria os quatro de forma idempotente.

## Estrutura do monorepo

```
editais/
├── Makefile              # atalhos (make help)
├── docker-compose.yaml   # infraestrutura (apps entram conforme implementados)
├── .env.example          # modelo do .env (ver seção acima)
├── docker/
│   ├── up-env.sh         # garante o .env antes do up
│   └── postgres/
│       └── init-schemas.sql  # 1 schema + 1 role por serviço
├── services/             # backends (Laravel e FastAPI)
│   ├── gestao-interna/   ├── bff-gateway/      ├── proposta-service/
│   ├── edital-service/   ├── avaliacao-service/└── notification-service/
└── apps/                 # frontends
    ├── app-web/          └── app-mobile/
```
