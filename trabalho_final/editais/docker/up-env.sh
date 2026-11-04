#!/bin/sh
# Garante o `.env` antes do `up`. Idempotente: só cria o que falta, nunca
# sobrescreve o que já existe.
#
# O modelo (`.env.example`) já vem com os valores do laboratório, então a
# cópia sobe pronta — ver o comentário no topo do próprio modelo sobre por
# que isso é aceitável aqui e não seria num projeto real.
set -e

RAIZ="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"

if [ -e "$RAIZ/.env" ]; then
    echo ".env: ok"
elif [ ! -f "$RAIZ/.env.example" ]; then
    echo ".env: ausente, e o modelo '.env.example' também não existe" >&2
    exit 1
else
    cp "$RAIZ/.env.example" "$RAIZ/.env"
    echo ".env: criado a partir de .env.example"
fi

# O .env não pode ficar para trás do modelo: toda variável do .env.example
# precisa existir também no .env. Só avisa — valor diferente é escolha sua,
# chave ausente é esquecimento.
faltantes=""
for chave in $(sed -n 's/^\([A-Za-z_][A-Za-z0-9_]*\)=.*/\1/p' "$RAIZ/.env.example" | sort -u); do
    grep -q "^${chave}=" "$RAIZ/.env" || faltantes="$faltantes $chave"
done
if [ -n "$faltantes" ]; then
    echo "AVISO: o .env está sem estas variáveis do .env.example:$faltantes" >&2
    echo "       copie as linhas que faltam (ou apague o .env e rode make env-up)." >&2
fi
