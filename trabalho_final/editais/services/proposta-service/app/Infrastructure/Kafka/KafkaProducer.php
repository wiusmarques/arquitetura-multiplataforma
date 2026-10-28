<?php

namespace App\Infrastructure\Kafka;

use RdKafka\Conf;
use RdKafka\Producer;

/** Adapter de saída para o Kafka (extensão rdkafka). Usado só pelo relay. */
class KafkaProducer
{
    private Producer $producer;

    public function __construct()
    {
        $conf = new Conf();
        $conf->set('bootstrap.servers', env('KAFKA_BOOTSTRAP', 'kafka:9092'));
        $this->producer = new Producer($conf);
    }

    public function publicar(string $topico, string $chave, array $payload): void
    {
        $this->producer->newTopic($topico)->produce(
            RD_KAFKA_PARTITION_UA,
            0,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            $chave,
        );
        $this->producer->poll(0);
    }

    /** Garante a entrega do que está no buffer antes de confirmar o lote. */
    public function flush(int $timeoutMs = 10000): void
    {
        $restante = $this->producer->flush($timeoutMs);
        if ($restante > 0) {
            throw new \RuntimeException("Kafka: {$restante} mensagens não confirmadas no flush.");
        }
    }
}
