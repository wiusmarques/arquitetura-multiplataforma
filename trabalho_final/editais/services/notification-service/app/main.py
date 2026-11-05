"""notification-service — consumer de todos os tópicos de domínio.

Padrão Observer distribuído (Encontro 3): este serviço é um observer que se
inscreve nos tópicos com o próprio consumer group — recebe cópia independente
de cada evento, sem que os produtores saibam da existência dele.

Para cada evento, registra a notificação e envia e-mail via SMTP (Mailpit no
laboratório — na vida real, o relay do órgão). Endereços são simulados a
partir do username: <usuario>@exemplo.local.
"""

import asyncio
import json
import logging
import os
import smtplib
import uuid
from contextlib import asynccontextmanager
from email.message import EmailMessage

import asyncpg
from aiokafka import AIOKafkaConsumer
from fastapi import FastAPI

from app.emails import montar_email

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(name)s %(message)s")
log = logging.getLogger("notification-service")

DATABASE_URL = os.environ.get("DATABASE_URL", "postgresql://notificacao:notificacao@postgres:5432/editais")
KAFKA_BOOTSTRAP = os.environ.get("KAFKA_BOOTSTRAP", "kafka:9092")
SMTP_HOST = os.environ.get("SMTP_HOST", "mailpit")
SMTP_PORT = int(os.environ.get("SMTP_PORT", "1025"))
CONSUMER_GROUP = "notification-service"
TOPICOS = ["edital.publicado", "proposta.submetida", "proposta.avaliada", "resultado.publicado"]

DDL = """
CREATE TABLE IF NOT EXISTS notificacoes (
    id         uuid PRIMARY KEY,
    topico     text NOT NULL,
    destinatario text NOT NULL,
    assunto    text NOT NULL,
    payload    jsonb NOT NULL,
    enviada_em timestamptz NOT NULL DEFAULT now()
);
"""


def enviar_smtp(destinatario: str, assunto: str, corpo: str) -> None:
    msg = EmailMessage()
    msg["From"] = "plataforma@editais.local"
    msg["To"] = destinatario
    msg["Subject"] = assunto
    msg.set_content(corpo)
    with smtplib.SMTP(SMTP_HOST, SMTP_PORT, timeout=10) as smtp:
        smtp.send_message(msg)


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
    loop = asyncio.get_running_loop()
    try:
        async for msg in consumer:
            try:
                destinatario, assunto, corpo = montar_email(msg.topic, msg.value)
                # smtplib é síncrono — roda no executor para não travar o loop.
                await loop.run_in_executor(None, enviar_smtp, destinatario, assunto, corpo)
                await pool.execute(
                    "INSERT INTO notificacoes (id, topico, destinatario, assunto, payload) "
                    "VALUES ($1, $2, $3, $4, $5::jsonb)",
                    str(uuid.uuid4()), msg.topic, destinatario, assunto,
                    json.dumps(msg.value, default=str),
                )
                log.info("e-mail enviado: %s -> %s", msg.topic, destinatario)
            except Exception:
                log.exception("falha ao notificar evento %s: %s", msg.topic, msg.value)
    finally:
        await consumer.stop()


@asynccontextmanager
async def lifespan(app: FastAPI):
    app.state.pool = await asyncpg.create_pool(DATABASE_URL, min_size=1, max_size=5)
    async with app.state.pool.acquire() as conn:
        await conn.execute(DDL)
    app.state.consumer_task = asyncio.create_task(consumir(app.state.pool))
    yield
    app.state.consumer_task.cancel()
    await app.state.pool.close()


app = FastAPI(title="notification-service", description=__doc__, lifespan=lifespan)

# Observabilidade (no-op sem OTEL_EXPORTER_OTLP_ENDPOINT no ambiente).
from app.telemetry import setup_telemetry  # noqa: E402

setup_telemetry(app, "notification-service")


@app.get("/health")
async def health():
    return {"status": "ok", "servico": "notification-service"}


@app.get("/v1/notificacoes")
async def listar_notificacoes(topico: str | None = None):
    sql, args = "SELECT * FROM notificacoes", []
    if topico:
        sql += " WHERE topico = $1"
        args.append(topico)
    sql += " ORDER BY enviada_em DESC LIMIT 100"
    rows = await app.state.pool.fetch(sql, *args)
    return [dict(r) for r in rows]
