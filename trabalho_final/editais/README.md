# Plataforma de Editais Federais

Monorepo do trabalho final de **Arquitetura de Software Multiplataforma** (PUC Minas). Plataforma para gestão de editais federais de fomento: a administração publica editais, pessoas físicas e jurídicas submetem propostas solicitando recursos, avaliadores externos emitem pareceres e os resultados são públicos, com API de transparência para órgãos de controle.

Arquitetura, diagramas C4 e justificativas em [`../documentacao/`](../documentacao/) (`decisoes-arquiteturais.md` e `diagramas/c4-editais.drawio`).

## Serviços, URLs e portas

Cada serviço tem hostname e porta próprios (ver [Configurar os hosts](#configurar-os-hosts-da-máquina)); quase tudo responde também em `http://localhost:<porta>`. As portas abaixo são as do `.env.example` — se o seu `.env` mudou alguma, vale o valor dele, e `make ps` mostra as portas realmente publicadas.

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

Acrescente **duas linhas** ao arquivo de hosts, com os hostnames do ambiente (o alvo `make hosts` as imprime prontas):

```
127.0.0.1 app.editais.local gestao.editais.local bff.editais.local edital.editais.local proposta.editais.local avaliacao.editais.local notificacao.editais.local
127.0.0.1 idp.editais.local mail.editais.local kafka.editais.local grafana.editais.local
```

> **Duas linhas, não uma.** O resolvedor de nomes — tanto o do Windows quanto o glibc — lê no máximo **9 nomes por linha** do arquivo hosts: do 10º em diante ele ignora em silêncio, e o navegador responde `DNS_PROBE_FINISHED_NXDOMAIN` mesmo com o nome escrito lá. São 11 hostnames, por isso a divisão.

Obrigatórios de verdade são só os dois do fluxo OIDC — `idp.editais.local` (issuer dos JWTs: navegador e serviços precisam enxergar a mesma URL) e `gestao.editais.local` (redirect do login do painel de gestão). Os demais são conveniência: tudo também responde em `http://localhost:<porta>`.

- **Linux / macOS**: `sudo nano /etc/hosts`
- **Windows**: abrir o Bloco de Notas **como administrador** e editar `C:\Windows\System32\drivers\etc\hosts`
- **WSL2**: editar o hosts do **Windows** (acima); o `/etc/hosts` do WSL é regenerado a partir dele.

## Os três blocos do compose

O stack está descrito em **três arquivos**, que são **três projetos compose independentes** — `docker compose ls` e o Docker Desktop mostram os três lado a lado, em vez de um `editais` com tudo dentro:

| Projeto / arquivo | O que tem dentro | Por que está junto |
|---|---|---|
| **`editais-base`**<br>`docker-compose.base.yaml` | PostgreSQL, Kafka, Keycloak, `gestao-interna` + `gestao-relay` | O legado e o estado, e o que todo o resto pressupõe de pé. Mais réplicas aqui exigem decisão de infraestrutura (volume, quorum, sessão), não um `--scale`. |
| **`editais-services`**<br>`docker-compose.services.yaml` | `edital-service`, `proposta-service` (+ relay, consumer e pagamentos), `avaliacao-service`, `notification-service`, `bff-gateway` | Processos sem estado: dá para rodar N réplicas de qualquer um deles, porque o estado está todo no bloco de cima. |
| **`editais-viewers`**<br>`docker-compose.viewers.yaml` | `app-web`, Kafka UI, Mailpit | O que se olha: o frontend do proponente e as UIs de inspeção do laboratório. Derrubar o bloco inteiro não para o fluxo de editais. É aqui que Grafana e Prometheus entram quando a observabilidade for implementada. |

O critério da divisão é o papel de cada camada — **o que sustenta, o que escala e o que se olha**. A separação é organizacional: mesmos serviços, portas, variáveis e imagens de antes.

> O Mailpit é a exceção que vale registrar: além da UI em `:8025`, ele é o **servidor SMTP** (`:1025`) para onde o `notification-service` manda e-mail. Está no `viewers` pela UI; o SMTP continua respondendo pela rede, de qualquer bloco.

Três consequências de serem projetos distintos, e como o repositório lida com elas:

- **Rede** — projetos diferentes não compartilham a rede default, então os três usam uma rede **externa** chamada `editais`, criada pelo alvo `make infra` (que o `make up` já chama). Com isso o monolito e os serviços novos continuam se falando por nome de container, como numa migração real em que o legado ainda está no mesmo datacenter.
- **Ordem de subida** — `depends_on` só enxerga serviços do mesmo projeto. A espera pelo banco, pelo Kafka e pelo IdP subiu um nível: `make up` sobe o `editais-base` com `--wait` (healthchecks) e só então os outros dois. Dentro de cada bloco, os `depends_on` internos continuam valendo; e todo serviço tem `restart: unless-stopped`, então quem subir adiantado apenas reinicia até conectar.
- **Volumes** — pelo mesmo motivo, `editais_pg_data` e `editais_kafka_data` também são **externos** (nome fixo, não derivado do projeto): o estado do laboratório não pode depender de como os blocos estão arranjados. `make infra` os cria junto com a rede; só o `make reset` os apaga, explicitamente.

## Subindo o ambiente

Pré-requisitos: Docker + Docker Compose v2 e `make`.

```bash
make build      # constrói as imagens dos serviços da aplicação
make up         # garante o .env e sobe o stack inteiro (os três blocos)
make topics-up  # cria os tópicos Kafka do domínio
make ps         # confere os healthchecks
```

São **três projetos compose** (ver a seção acima), mas um comando só: cada alvo age nos três blocos, na ordem certa (a de descida é a inversa). `make ps` imprime uma tabela por bloco.

`make help` lista todos os alvos (`logs`, `psql`, `hosts`, `down`, `reset`…). Depois de mexer no código ou no Dockerfile de um serviço, `make build` regenera a imagem (`make build S=proposta-service` para só um) e `make up` recria o container.

Cada bloco também anda sozinho, o que é útil para demonstrar o legado de pé antes dos serviços:

```bash
docker compose -f docker-compose.base.yaml up -d        # só banco, broker, IdP e monolito
docker compose -f docker-compose.services.yaml up -d    # só os microsserviços e o BFF
docker compose -f docker-compose.viewers.yaml up -d     # só o app web e as UIs
```

A única exigência é a rede e os volumes já existirem (`make infra`) e, para os blocos `services` e `viewers`, o `base` estar de pé — é dele que vêm banco, broker e IdP.

> Para demonstrar réplicas (`up -d --scale proposta-service=3`), comente o `container_name` do serviço no bloco `services`: nome fixo de container e escala não convivem. O alias de rede é o nome do **serviço**, então quem chama `proposta-service:8002` passa a cair em qualquer réplica.

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

## Testes e hook de pre-push

Dois conjuntos de testes unitários exemplificam o gate de qualidade local —
um por plataforma, rodando em segundos, **sem container nenhum**:

- `services/proposta-service/tests/PropostaTest.php` — invariantes do
  Aggregate Root `Proposta` (avaliação e pagamento), em PHP puro. Usa um
  mini-runner (`tests/run.php`) em vez de PHPUnit porque o `vendor/` só
  existe dentro da imagem Docker.
- `services/notification-service/tests/test_emails.py` — montagem dos
  e-mails de notificação, com o `unittest` da stdlib do Python.

```sh
make test    # roda os dois conjuntos
make hooks   # instala o pre-push (uma vez por clone)
```

O `make hooks` aponta o `core.hooksPath` do git para a pasta versionada
`.githooks/`. A partir daí **todo `git push` roda `make test` antes de
enviar** — teste quebrado, push recusado, em qualquer branch (inclusive a
main/master). Para ver a recusa acontecendo, descomente o bloco indicado no
final do `PropostaTest.php` e tente um push; comente de novo e o push volta
a passar.

## Estrutura do monorepo

```
editais/
├── Makefile              # atalhos (make help)
├── docker-compose.base.yaml      # bloco base: banco, Kafka, IdP, monolito
├── docker-compose.services.yaml  # bloco services: microsserviços, workers, BFF
├── docker-compose.viewers.yaml   # bloco viewers: app web, Kafka UI, Mailpit
├── .env.example          # modelo do .env (ver seção acima)
├── .githooks/
│   └── pre-push          # gate local: roda `make test` antes de todo push
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
