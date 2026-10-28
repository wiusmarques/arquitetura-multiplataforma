# Atalhos do ambiente da Plataforma de Editais Federais.
#
# Convenção: `make` sem argumento cai no `help`; todo alvo que sobe container
# depende de `env-up`, que garante o `.env` a partir do modelo (`.env.example`).
# O compose lê o `.env` sozinho para interpolar credenciais e portas.

COMPOSE ?= docker compose

TOPICOS = edital.publicado proposta.submetida proposta.avaliada resultado.publicado
# Linha completa de hosts. Obrigatórios só os do fluxo OIDC (idp e gestao);
# os demais são conveniência — tudo também responde em localhost:<porta>.
HOSTS   = app.editais.local gestao.editais.local bff.editais.local \
          edital.editais.local proposta.editais.local avaliacao.editais.local \
          notificacao.editais.local idp.editais.local mail.editais.local \
          kafka.editais.local grafana.editais.local

.PHONY: help env-up up down restart ps logs topics-up psql hosts kc-reset reset

help: ## Lista os alvos disponíveis
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

env-up: ## Cria o `.env` se faltar, a partir do modelo
	@sh ./docker/up-env.sh

up: env-up ## Sobe a infraestrutura (Postgres, Kafka, Keycloak, Mailpit, Kafka UI)
	$(COMPOSE) up -d

down: ## Derruba o stack (volumes preservados)
	$(COMPOSE) down

restart: env-up ## Recria os containers, relendo o `.env`
	$(COMPOSE) up -d --force-recreate

ps: ## Estado dos containers (inclui healthcheck)
	$(COMPOSE) ps

logs: ## Acompanha os logs do stack
	$(COMPOSE) logs -f --tail=100

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

hosts: ## Mostra a linha a acrescentar no arquivo hosts da máquina
	@echo "127.0.0.1 $(HOSTS)"

# O --import-realm só importa quando o realm ainda não existe (estratégia
# IGNORE_EXISTING): mudou o editais-realm.json, é preciso recriar o database
# `keycloak` no Postgres para reimportar. O database `editais` (domínio) e o
# Kafka ficam intactos.
kc-reset: env-up ## Recria o banco do Keycloak e reimporta o realm `editais`
	$(COMPOSE) rm -sf keycloak
	docker exec editais-postgres psql -U $${POSTGRES_USER:-editais} -d postgres \
		-c "DROP DATABASE IF EXISTS keycloak WITH (FORCE);" \
		-c "CREATE DATABASE keycloak OWNER keycloak;"
	$(COMPOSE) up -d keycloak

reset: ## Derruba tudo e APAGA os volumes (banco, Kafka, Keycloak)
	$(COMPOSE) down -v
