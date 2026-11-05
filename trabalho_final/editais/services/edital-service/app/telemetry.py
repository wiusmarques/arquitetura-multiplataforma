"""OpenTelemetry — os três pilares da aula 4, via OTLP HTTP para o Collector.

  serviço → OTel Collector → Tempo      (traces)
                           → Prometheus (métricas)
                           → Loki       (logs)

Só ativa quando OTEL_EXPORTER_OTLP_ENDPOINT está no ambiente (o compose do
bloco observability define); sem ele o serviço roda exatamente como antes.
A instrumentação do aiokafka propaga o contexto do trace nos HEADERS da
mensagem — é o que faz o traceID atravessar o broker em vez de morrer nele.
"""
import logging
import os

log = logging.getLogger(__name__)


def setup_telemetry(app, service_name: str) -> None:
    endpoint = os.getenv("OTEL_EXPORTER_OTLP_ENDPOINT")
    if not endpoint:
        return

    from opentelemetry import metrics, trace
    from opentelemetry._logs import set_logger_provider
    from opentelemetry.exporter.otlp.proto.http._log_exporter import OTLPLogExporter
    from opentelemetry.exporter.otlp.proto.http.metric_exporter import OTLPMetricExporter
    from opentelemetry.exporter.otlp.proto.http.trace_exporter import OTLPSpanExporter
    from opentelemetry.instrumentation.aiokafka import AIOKafkaInstrumentor
    from opentelemetry.instrumentation.asyncpg import AsyncPGInstrumentor
    from opentelemetry.instrumentation.fastapi import FastAPIInstrumentor
    from opentelemetry.instrumentation.logging import LoggingInstrumentor
    from opentelemetry.sdk._logs import LoggerProvider
    from opentelemetry.sdk._logs.export import BatchLogRecordProcessor
    from opentelemetry.sdk.metrics import MeterProvider
    from opentelemetry.sdk.metrics.export import PeriodicExportingMetricReader
    from opentelemetry.sdk.resources import Resource
    from opentelemetry.sdk.trace import TracerProvider
    from opentelemetry.sdk.trace.export import BatchSpanProcessor

    resource = Resource.create({"service.name": service_name})

    # Traces → Collector → Tempo
    tracer_provider = TracerProvider(resource=resource)
    tracer_provider.add_span_processor(
        BatchSpanProcessor(OTLPSpanExporter(endpoint=f"{endpoint}/v1/traces"))
    )
    trace.set_tracer_provider(tracer_provider)

    # Métricas → Collector → Prometheus
    reader = PeriodicExportingMetricReader(
        OTLPMetricExporter(endpoint=f"{endpoint}/v1/metrics"),
        export_interval_millis=15_000,
    )
    metrics.set_meter_provider(MeterProvider(resource=resource, metric_readers=[reader]))

    # Logs → Collector → Loki (o LoggingInstrumentor instala o handler OTel
    # no root logger e injeta traceId/spanId — correlação no Grafana).
    logger_provider = LoggerProvider(resource=resource)
    logger_provider.add_log_record_processor(
        BatchLogRecordProcessor(OTLPLogExporter(endpoint=f"{endpoint}/v1/logs"))
    )
    set_logger_provider(logger_provider)
    LoggingInstrumentor().instrument(set_logging_format=True, log_code_attributes=True)

    # Auto-instrumentação: um span por request HTTP, por query e por
    # mensagem Kafka (produzida ou consumida), com contexto propagado.
    FastAPIInstrumentor.instrument_app(app)
    AsyncPGInstrumentor().instrument()
    AIOKafkaInstrumentor().instrument()

    log.info("OpenTelemetry ativo: endpoint=%s service=%s", endpoint, service_name)
