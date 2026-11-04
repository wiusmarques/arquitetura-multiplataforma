"""Montagem dos e-mails de notificação — lógica PURA, separada do I/O.

Módulo sem FastAPI, asyncpg ou Kafka de propósito: é a parte do serviço que
dá para testar com unittest puro (ver tests/), sem subir container nenhum.
O main.py importa daqui; quem faz SMTP e consome tópico é ele.
"""

import json


def montar_email(topico: str, ev: dict) -> tuple[str, str, str]:
    """Decide destinatário, assunto e corpo conforme o evento."""
    if topico == "edital.publicado":
        return ("interessados@exemplo.local",
                f"Novo edital publicado: {ev.get('titulo', '')}",
                f"O edital \"{ev.get('titulo')}\" foi publicado. "
                f"Valor total: R$ {ev.get('valor_total')}. "
                f"Encerramento: {ev.get('data_encerramento')}.")
    if topico == "proposta.submetida":
        return (f"{ev.get('proponente', 'proponente')}@exemplo.local",
                "Proposta recebida",
                f"Sua proposta \"{ev.get('titulo')}\" foi recebida e aguarda avaliação. "
                f"Protocolo: {ev.get('proposta_id')}.")
    if topico == "proposta.avaliada":
        return (f"{ev.get('proponente', 'proponente')}@exemplo.local",
                f"Proposta {ev.get('resultado')}",
                f"Sua proposta foi {ev.get('resultado')}. Parecer: {ev.get('parecer', '')}")
    if topico == "resultado.publicado":
        return ("interessados@exemplo.local",
                "Resultado de edital publicado",
                f"O resultado do edital {ev.get('edital_id')} foi publicado: "
                f"{json.dumps(ev.get('resumo', {}), ensure_ascii=False)}")
    return ("interessados@exemplo.local", f"Evento {topico}", json.dumps(ev, ensure_ascii=False))
