"""edital-service — Query Side do CQRS.

Lado de LEITURA dos editais: não tem regra de negócio nem endpoint de
escrita. O read model (schema editais_read) é populado exclusivamente por
eventos Kafka vindos do command side (monolito gestao-interna) e dos demais
serviços — nunca por transação compartilhada.

Também serve a API pública de transparência (distribuição de recursos e
prestação de contas), que é só leitura e tolera consistência eventual —
ver documentacao/decisoes-arquiteturais.md.
"""

import asyncio
import json
import logging
import os
from contextlib import asynccontextmanager
from datetime import date

import asyncpg
from aiokafka import AIOKafkaConsumer
from fastapi import FastAPI, HTTPException

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(name)s %(message)s")
log = logging.getLogger("edital-service")

DATABASE_URL = os.environ.get("DATABASE_URL", "postgresql://edital:edital@postgres:5432/editais")
KAFKA_BOOTSTRAP = os.environ.get("KAFKA_BOOTSTRAP", "kafka:9092")
CONSUMER_GROUP = "edital-service"
TOPICOS = ["edital.publicado", "resultado.publicado", "proposta.submetida", "proposta.avaliada"]

# A role `edital` tem search_path = editais_read (ver docker/postgres/init-schemas.sql),
# então as tabelas nascem no schema do serviço. Read model desnormalizado de
# propósito: a leitura pública não faz JOIN com o domínio de ninguém.
DDL = """
CREATE TABLE IF NOT EXISTS editais (
    id              uuid PRIMARY KEY,
    titulo          text NOT NULL,
    descricao       text NOT NULL DEFAULT '',
    valor_total     numeric(14,2) NOT NULL,
    valor_por_candidato numeric(14,2),
    numero_candidatos integer,
    nota_maxima     numeric(5,2),
    data_abertura   date,
    data_encerramento date,
    status          text NOT NULL DEFAULT 'publicado',
    resultado       jsonb,
    atualizado_em   timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE IF NOT EXISTS transparencia (
    edital_id           uuid PRIMARY KEY,
    propostas_recebidas integer NOT NULL DEFAULT 0,
    propostas_aprovadas integer NOT NULL DEFAULT 0,
    propostas_reprovadas integer NOT NULL DEFAULT 0,
    valor_aprovado      numeric(14,2) NOT NULL DEFAULT 0,
    atualizado_em       timestamptz NOT NULL DEFAULT now()
);
"""


def _data(valor: str | None) -> date | None:
    """Datas chegam como string ISO no evento; o asyncpg exige datetime.date."""
    return date.fromisoformat(valor) if valor else None


async def aplicar_evento(pool: asyncpg.Pool, topico: str, evento: dict) -> None:
    """Projeta um evento de domínio no read model (sem regra de negócio)."""
    async with pool.acquire() as conn:
        if topico == "edital.publicado":
            await conn.execute(
                """
                INSERT INTO editais (id, titulo, descricao, valor_total, valor_por_candidato,
                                     numero_candidatos, nota_maxima, data_abertura, data_encerramento, status)
                VALUES ($1, $2, $3, $4, $5, $6, $7, $8::date, $9::date, 'publicado')
                ON CONFLICT (id) DO UPDATE SET
                    titulo = EXCLUDED.titulo, descricao = EXCLUDED.descricao,
                    valor_total = EXCLUDED.valor_total,
                    valor_por_candidato = EXCLUDED.valor_por_candidato,
                    numero_candidatos = EXCLUDED.numero_candidatos,
                    nota_maxima = EXCLUDED.nota_maxima,
                    data_abertura = EXCLUDED.data_abertura,
                    data_encerramento = EXCLUDED.data_encerramento,
                    status = 'publicado', atualizado_em = now()
                """,
                evento["edital_id"], evento["titulo"], evento.get("descricao", ""),
                evento["valor_total"], evento.get("valor_por_candidato"),
                evento.get("numero_candidatos"), evento.get("nota_maxima"),
                _data(evento.get("data_abertura")), _data(evento.get("data_encerramento")),
            )
            await conn.execute(
                "INSERT INTO transparencia (edital_id) VALUES ($1) ON CONFLICT DO NOTHING",
                evento["edital_id"],
            )
        elif topico == "resultado.publicado":
            await conn.execute(
                """
                UPDATE editais SET status = 'resultado_publicado',
                       resultado = $2::jsonb, atualizado_em = now()
                WHERE id = $1
                """,
                evento["edital_id"], json.dumps(evento.get("resumo", {})),
            )
        elif topico == "proposta.submetida":
            await conn.execute(
                """
                INSERT INTO transparencia (edital_id, propostas_recebidas)
                VALUES ($1, 1)
                ON CONFLICT (edital_id) DO UPDATE SET
                    propostas_recebidas = transparencia.propostas_recebidas + 1,
                    atualizado_em = now()
                """,
                evento["edital_id"],
            )
        elif topico == "proposta.avaliada":
            # Proposta não tem valor próprio: aprovado recebe o
            # valor_por_candidato do edital.
            aprovada = evento.get("resultado") == "aprovada"
            await conn.execute(
                """
                UPDATE transparencia SET
                    propostas_aprovadas = propostas_aprovadas + $2,
                    propostas_reprovadas = propostas_reprovadas + $3,
                    valor_aprovado = valor_aprovado + CASE WHEN $2 = 1 THEN
                        COALESCE((SELECT valor_por_candidato FROM editais WHERE id = $1), 0)
                        ELSE 0 END,
                    atualizado_em = now()
                WHERE edital_id = $1
                """,
                evento["edital_id"], 1 if aprovada else 0, 0 if aprovada else 1,
            )


async def consumir(pool: asyncpg.Pool) -> None:
    consumer = AIOKafkaConsumer(
        *TOPICOS,
        bootstrap_servers=KAFKA_BOOTSTRAP,
        group_id=CONSUMER_GROUP,
        value_deserializer=lambda v: json.loads(v.decode()),
        auto_offset_reset="earliest",
    )
    await consumer.start()
    log.info("consumindo %s (group=%s)", TOPICOS, CONSUMER_GROUP)
    try:
        async for msg in consumer:
            try:
                await aplicar_evento(pool, msg.topic, msg.value)
                log.info("evento aplicado: %s %s", msg.topic, msg.value.get("edital_id"))
            except Exception:
                log.exception("falha ao aplicar evento %s: %s", msg.topic, msg.value)
    finally:
        await consumer.stop()


@asynccontextmanager
async def lifespan(app: FastAPI):
    app.state.pool = await asyncpg.create_pool(DATABASE_URL, min_size=1, max_size=10)
    async with app.state.pool.acquire() as conn:
        await conn.execute(DDL)
    app.state.consumer_task = asyncio.create_task(consumir(app.state.pool))
    # O consumer roda como task do event loop: sem este callback, uma falha
    # nele (ex.: Kafka indisponível no start) morreria em silêncio.
    app.state.consumer_task.add_done_callback(
        lambda t: t.cancelled() or t.exception() is None
        or log.error("consumer encerrou com erro", exc_info=t.exception())
    )
    yield
    app.state.consumer_task.cancel()
    await app.state.pool.close()


app = FastAPI(title="edital-service", description=__doc__, lifespan=lifespan)


@app.get("/health")
async def health():
    return {"status": "ok", "servico": "edital-service"}


@app.get("/v1/editais")
async def listar_editais(status: str | None = None):
    sql = "SELECT * FROM editais"
    args: list = []
    if status:
        sql += " WHERE status = $1"
        args.append(status)
    sql += " ORDER BY data_encerramento NULLS LAST, titulo"
    rows = await app.state.pool.fetch(sql, *args)
    return [dict(r) for r in rows]


@app.get("/v1/editais/{edital_id}")
async def consultar_edital(edital_id: str):
    row = await app.state.pool.fetchrow("SELECT * FROM editais WHERE id = $1::uuid", edital_id)
    if not row:
        raise HTTPException(status_code=404, detail="Edital não encontrado")
    return dict(row)


@app.get("/v1/transparencia/recursos")
async def transparencia_recursos():
    """Distribuição de recursos por edital — consumida por órgãos de controle
    e portais de dados abertos (somente leitura, consistência eventual)."""
    rows = await app.state.pool.fetch(
        """
        SELECT e.id AS edital_id, e.titulo, e.status, e.valor_total,
               t.propostas_recebidas, t.propostas_aprovadas, t.propostas_reprovadas,
               t.valor_aprovado, t.atualizado_em
        FROM editais e JOIN transparencia t ON t.edital_id = e.id
        ORDER BY e.titulo
        """
    )
    return [dict(r) for r in rows]


@app.get("/v1/transparencia/prestacao-contas/{edital_id}")
async def prestacao_contas(edital_id: str):
    row = await app.state.pool.fetchrow(
        """
        SELECT e.id AS edital_id, e.titulo, e.status, e.valor_total, e.resultado,
               t.propostas_recebidas, t.propostas_aprovadas, t.propostas_reprovadas,
               t.valor_aprovado, t.atualizado_em
        FROM editais e JOIN transparencia t ON t.edital_id = e.id
        WHERE e.id = $1::uuid
        """,
        edital_id,
    )
    if not row:
        raise HTTPException(status_code=404, detail="Edital não encontrado")
    return dict(row)
