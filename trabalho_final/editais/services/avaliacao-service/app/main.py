"""avaliacao-service — distribuição de propostas e registro de pareceres.

Consome `proposta.submetida` (cria a tarefa de avaliação), expõe REST para o
monolito atribuir avaliadores e para o avaliador (via BFF, role `avaliador`)
consultar as tarefas dele e registrar o parecer — que publica
`proposta.avaliada` para os demais serviços reagirem.

A identidade do avaliador chega no header `X-Usuario`, injetado pelo BFF a
partir do JWT validado (o cliente nunca fala direto com este serviço).
"""

import asyncio
import json
import logging
import os
import uuid
from contextlib import asynccontextmanager
from datetime import datetime, timezone

import asyncpg
from aiokafka import AIOKafkaConsumer, AIOKafkaProducer
from fastapi import FastAPI, Header, HTTPException
from pydantic import BaseModel

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(name)s %(message)s")
log = logging.getLogger("avaliacao-service")

DATABASE_URL = os.environ.get("DATABASE_URL", "postgresql://avaliacao:avaliacao@postgres:5432/editais")
KAFKA_BOOTSTRAP = os.environ.get("KAFKA_BOOTSTRAP", "kafka:9092")
CONSUMER_GROUP = "avaliacao-service"

DDL = """
CREATE TABLE IF NOT EXISTS tarefas (
    id               uuid PRIMARY KEY,
    proposta_id      uuid NOT NULL UNIQUE,
    edital_id        uuid NOT NULL,
    proponente       text NOT NULL,
    titulo_proposta  text NOT NULL DEFAULT '',
    descricao        text NOT NULL DEFAULT '',
    retorno_esperado text NOT NULL DEFAULT '',
    avaliador        text,
    status           text NOT NULL DEFAULT 'pendente', -- pendente | atribuida | concluida
    resultado        text,                             -- aprovada | reprovada
    parecer          text,
    criada_em        timestamptz NOT NULL DEFAULT now(),
    concluida_em     timestamptz
);
"""


class AtribuirAvaliador(BaseModel):
    avaliador: str


class RegistrarParecer(BaseModel):
    resultado: str  # aprovada | reprovada
    parecer: str


async def consumir(pool: asyncpg.Pool) -> None:
    consumer = AIOKafkaConsumer(
        "proposta.submetida",
        bootstrap_servers=KAFKA_BOOTSTRAP,
        group_id=CONSUMER_GROUP,
        value_deserializer=lambda v: json.loads(v.decode()),
        auto_offset_reset="earliest",
    )
    await consumer.start()
    log.info("consumindo proposta.submetida (group=%s)", CONSUMER_GROUP)
    try:
        async for msg in consumer:
            ev = msg.value
            try:
                await pool.execute(
                    """
                    INSERT INTO tarefas (id, proposta_id, edital_id, proponente, titulo_proposta, descricao, retorno_esperado)
                    VALUES ($1, $2, $3, $4, $5, $6, $7)
                    ON CONFLICT (proposta_id) DO NOTHING
                    """,
                    str(uuid.uuid4()), ev["proposta_id"], ev["edital_id"],
                    ev.get("proponente", ""), ev.get("titulo", ""),
                    ev.get("descricao", ""), ev.get("retorno_esperado", ""),
                )
                log.info("tarefa criada para proposta %s", ev["proposta_id"])
            except Exception:
                log.exception("falha ao criar tarefa: %s", ev)
    finally:
        await consumer.stop()


@asynccontextmanager
async def lifespan(app: FastAPI):
    app.state.pool = await asyncpg.create_pool(DATABASE_URL, min_size=1, max_size=10)
    async with app.state.pool.acquire() as conn:
        await conn.execute(DDL)
    app.state.producer = AIOKafkaProducer(
        bootstrap_servers=KAFKA_BOOTSTRAP,
        value_serializer=lambda v: json.dumps(v, default=str).encode(),
    )
    await app.state.producer.start()
    app.state.consumer_task = asyncio.create_task(consumir(app.state.pool))
    # Sem este callback, uma falha no consumer morreria em silêncio.
    app.state.consumer_task.add_done_callback(
        lambda t: t.cancelled() or t.exception() is None
        or log.error("consumer encerrou com erro", exc_info=t.exception())
    )
    yield
    app.state.consumer_task.cancel()
    await app.state.producer.stop()
    await app.state.pool.close()


app = FastAPI(title="avaliacao-service", description=__doc__, lifespan=lifespan)


@app.get("/health")
async def health():
    return {"status": "ok", "servico": "avaliacao-service"}


@app.get("/v1/tarefas")
async def listar_tarefas(avaliador: str | None = None, edital_id: str | None = None,
                         status: str | None = None):
    sql, args = "SELECT * FROM tarefas WHERE true", []
    if avaliador:
        args.append(avaliador)
        sql += f" AND avaliador = ${len(args)}"
    if edital_id:
        args.append(edital_id)
        sql += f" AND edital_id = ${len(args)}::uuid"
    if status:
        args.append(status)
        sql += f" AND status = ${len(args)}"
    sql += " ORDER BY criada_em"
    rows = await app.state.pool.fetch(sql, *args)
    return [dict(r) for r in rows]


@app.put("/v1/tarefas/{tarefa_id}/avaliador")
async def atribuir_avaliador(tarefa_id: str, corpo: AtribuirAvaliador):
    """Chamado pelo monolito gestao-interna (REST): o admin atribui o avaliador."""
    row = await app.state.pool.fetchrow(
        """
        UPDATE tarefas SET avaliador = $2, status = 'atribuida'
        WHERE id = $1::uuid AND status <> 'concluida'
        RETURNING *
        """,
        tarefa_id, corpo.avaliador,
    )
    if not row:
        raise HTTPException(status_code=404, detail="Tarefa não encontrada ou já concluída")
    return dict(row)


@app.post("/v1/tarefas/{tarefa_id}/parecer")
async def registrar_parecer(tarefa_id: str, corpo: RegistrarParecer,
                            x_usuario: str = Header(default="")):
    if corpo.resultado not in ("aprovada", "reprovada"):
        raise HTTPException(status_code=422, detail="resultado deve ser 'aprovada' ou 'reprovada'")
    row = await app.state.pool.fetchrow(
        "SELECT * FROM tarefas WHERE id = $1::uuid", tarefa_id
    )
    if not row:
        raise HTTPException(status_code=404, detail="Tarefa não encontrada")
    if row["status"] == "concluida":
        raise HTTPException(status_code=409, detail="Parecer já registrado")
    if x_usuario and row["avaliador"] and row["avaliador"] != x_usuario:
        raise HTTPException(status_code=403, detail="Tarefa atribuída a outro avaliador")

    atualizada = await app.state.pool.fetchrow(
        """
        UPDATE tarefas SET status = 'concluida', resultado = $2, parecer = $3,
               avaliador = COALESCE(avaliador, $4), concluida_em = now()
        WHERE id = $1::uuid RETURNING *
        """,
        tarefa_id, corpo.resultado, corpo.parecer, x_usuario or None,
    )
    evento = {
        "proposta_id": str(atualizada["proposta_id"]),
        "edital_id": str(atualizada["edital_id"]),
        "proponente": atualizada["proponente"],
        "avaliador": atualizada["avaliador"],
        "resultado": atualizada["resultado"],
        "parecer": atualizada["parecer"],
        "avaliada_em": datetime.now(timezone.utc).isoformat(),
    }
    # Publicação direta (sem outbox): o UPDATE já foi commitado e o
    # send_and_wait confirma a entrega. Fluxo interno de baixo volume — o
    # rigor do outbox fica onde há pico (submissão) e comando (gestão).
    await app.state.producer.send_and_wait("proposta.avaliada", evento)
    log.info("proposta.avaliada publicada: %s", evento["proposta_id"])
    return dict(atualizada)
