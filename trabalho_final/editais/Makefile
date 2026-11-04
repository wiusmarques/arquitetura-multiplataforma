# Atalhos do ambiente da Plataforma de Editais Federais.
#
# Convenção: `make` sem argumento cai no `help`; todo alvo que sobe container
# depende de `env-up`, que garante o `.env` a partir do modelo (`.env.example`).
# O compose lê o `.env` sozinho para interpolar credenciais e portas.

# O stack são TRÊS projetos compose independentes (ver o cabeçalho de cada
# arquivo), separados pelo papel de cada camada:
#
#   editais-base      banco, Kafka, IdP e o monolito `gestao-interna`
#   editais-services  microsserviços, workers e BFF — o que escala
#   editais-viewers   app web, Kafka UI e Mailpit — o que se olha
#
# Projetos separados de propósito: `docker compose ls` e o Docker Desktop
# mostram três grupos, e cada bloco sobe, desce e escala por conta própria.
#
# O que os une é a rede externa `$(REDE)`, criada pelo alvo `infra`. Daqui os
# alvos continuam sendo UM comando só para o stack inteiro: cada um age nos
# três blocos, na ordem certa (serviços e viewers dependem do banco e do
# broker, que estão no base — por isso o `up` espera o base com `--wait`).
COMPOSE   ?= docker compose
BASE       = $(COMPOSE) -f docker-compose.base.yaml
SERVICES   = $(COMPOSE) -f docker-compose.services.yaml
VIEWERS    = $(COMPOSE) -f docker-compose.viewers.yaml
# Ordem de subida; a de descida é esta invertida.
BLOCOS     = docker-compose.base.yaml docker-compose.services.yaml docker-compose.viewers.yaml
REDE       = editais
VOLUMES    = editais_pg_data editais_kafka_data

TOPICOS = edital.publicado proposta.submetida proposta.avaliada resultado.publicado
# Hosts do ambiente, em DUAS linhas de propósito: o resolvedor de nomes (tanto
# o do Windows quanto o glibc) lê no máximo 9 nomes por linha do arquivo hosts
# — o 10º em diante é silenciosamente ignorado e vira NXDOMAIN no navegador.
# Obrigatórios só os do fluxo OIDC (idp e gestao); os demais são conveniência
# — tudo também responde em localhost:<porta>.
HOSTS_APP   = app.editais.local gestao.editais.local bff.editais.local \
              edital.editais.local proposta.editais.local \
              avaliacao.editais.local notificacao.editais.local
HOSTS_INFRA = idp.editais.local mail.editais.local kafka.editais.local \
              grafana.editais.local

.PHONY: help env-up infra build up down restart ps logs topics-up psql hosts kc-reset reset

help: ## Lista os alvos disponíveis
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

env-up: ## Cria o `.env` se faltar, a partir do modelo
	@sh ./docker/up-env.sh

# Rede e volumes são externos aos três projetos: projetos compose diferentes
# não compartilham a rede default, e o estado (banco, Kafka) não pode depender
# de como os blocos estão arranjados. Criados uma vez, idempotente.
infra: ## Cria a rede e os volumes compartilhados pelos três blocos
	@docker network inspect $(REDE) >/dev/null 2>&1 \
		|| docker network create $(REDE)
	@for v in $(VOLUMES); do \
		docker volume inspect $$v >/dev/null 2>&1 || docker volume create $$v; \
	done

# O `up` só constrói a imagem que ainda não existe; depois de mexer no código
# ou no Dockerfile de um serviço é o `build` que regenera a imagem. Com
# `S=<serviço>` (ex.: `make build S=proposta-service`) o alvo descobre sozinho
# em qual dos três blocos aquele serviço mora.
build: env-up ## (Re)constrói as imagens dos serviços da aplicação [S=<serviço>]
	@for bloco in $(BLOCOS); do \
		if [ -n "$(S)" ] && ! $(COMPOSE) -f $$bloco config --services | grep -qx '$(S)'; then \
			continue; \
		fi; \
		$(COMPOSE) -f $$bloco build $(S); \
	done

# `--wait` no base: banco, Kafka e IdP saudáveis antes de os outros blocos
# subirem — é o que o `depends_on` fazia quando tudo era um projeto só.
up: env-up infra ## Sobe os três blocos (base, depois services e viewers)
	$(BASE) up -d --wait
	$(SERVICES) up -d
	$(VIEWERS) up -d

down: ## Derruba os três blocos (volumes e rede preservados)
	$(VIEWERS) down
	$(SERVICES) down
	$(BASE) down

restart: env-up infra ## Recria os containers, relendo o `.env`
	$(BASE) up -d --force-recreate --wait
	$(SERVICES) up -d --force-recreate
	$(VIEWERS) up -d --force-recreate

ps: ## Estado dos containers dos três blocos (inclui healthcheck)
	@for bloco in $(BLOCOS); do $(COMPOSE) -f $$bloco ps; done

# Um `logs -f` por bloco, em paralelo; o trap derruba todos no Ctrl-C.
logs: ## Acompanha os logs dos três blocos
	@trap 'kill 0' INT; \
	for bloco in $(BLOCOS); do $(COMPOSE) -f $$bloco logs -f --tail=100 & done; \
	wait

# Os tópicos são criados explicitamente (auto-create desligado no broker):
# partições e retenção são decisão de arquitetura, não efeito colateral de
# um producer qualquer. Idempotente (--if-not-exists).
topics-up: ## Cria os tópicos Kafka do domínio (idempotente)
	@for t in $(TOPICOS); do \
		docker exec editais-kafka kafka-topics --bootstrap-server localhost:9092 \
			--create --if-not-exists --topic $$t --partitions 3 --replication-factor 1; \
	done
	@docker exec editais-kafka kafka-topics --bootstrap-server localhost:9092 --list

psql: ## Abre um psql no Postgres (superusuário do laboratório)
	docker exec -it editais-postgres psql -U editais -d editais

hosts: ## Mostra as linhas a acrescentar no arquivo hosts da máquina
	@echo "127.0.0.1 $(HOSTS_APP)"
	@echo "127.0.0.1 $(HOSTS_INFRA)"

# O --import-realm só importa quando o realm ainda não existe (estratégia
# IGNORE_EXISTING): mudou o editais-realm.json, é preciso recriar o database
# `keycloak` no Postgres para reimportar. O database `editais` (domínio) e o
# Kafka ficam intactos.
kc-reset: env-up infra ## Recria o banco do Keycloak e reimporta o realm `editais`
	$(BASE) rm -sf keycloak
	docker exec editais-postgres psql -U $${POSTGRES_USER:-editais} -d postgres \
		-c "DROP DATABASE IF EXISTS keycloak WITH (FORCE);" \
		-c "CREATE DATABASE keycloak OWNER keycloak;"
	$(BASE) up -d keycloak

# Os volumes e a rede são externos, então o `down -v` não os leva junto:
# apagar o estado do laboratório é decisão explícita deste alvo.
reset: ## Derruba tudo e APAGA os volumes (banco, Kafka, Keycloak) e a rede
	$(VIEWERS) down
	$(SERVICES) down
	$(BASE) down
	-@docker volume rm $(VOLUMES) 2>/dev/null || true
	-@docker network rm $(REDE) 2>/dev/null || true
