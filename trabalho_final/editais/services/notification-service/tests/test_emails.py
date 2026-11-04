"""Testes unitários do notification-service (lado Python do `make test`).

Rodam com a biblioteca padrão (unittest) em segundos, sem container, banco
ou broker — por isso servem de gate no hook de pre-push. Só testam lógica
pura (app/emails.py); o I/O (SMTP, Kafka) fica de fora de propósito.
"""

import sys
import unittest
from pathlib import Path

# O serviço não é instalado como pacote: aponta o import para a raiz dele.
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from app.emails import montar_email


class MontarEmailTest(unittest.TestCase):

    def test_edital_publicado_notifica_lista_de_interessados(self):
        destinatario, assunto, corpo = montar_email(
            "edital.publicado",
            {"titulo": "Edital de Inovação 01/2026", "valor_total": 500000,
             "data_encerramento": "2026-12-01"},
        )
        self.assertEqual("interessados@exemplo.local", destinatario)
        self.assertIn("Edital de Inovação 01/2026", assunto)
        self.assertIn("R$ 500000", corpo)

    def test_proposta_avaliada_notifica_o_proponente(self):
        destinatario, assunto, corpo = montar_email(
            "proposta.avaliada",
            {"proponente": "maria", "resultado": "aprovada", "parecer": "Projeto sólido."},
        )
        self.assertEqual("maria@exemplo.local", destinatario)
        self.assertEqual("Proposta aprovada", assunto)
        self.assertIn("Projeto sólido.", corpo)

    def test_topico_desconhecido_cai_no_fallback_generico(self):
        destinatario, assunto, _ = montar_email("topico.novo", {"x": 1})
        self.assertEqual("interessados@exemplo.local", destinatario)
        self.assertEqual("Evento topico.novo", assunto)


if __name__ == "__main__":
    unittest.main()
