<?php

namespace App\Console\Commands;

use App\Application\RegistrarResultadoAvaliacao;
use Illuminate\Console\Command;
use RdKafka\Conf;
use RdKafka\KafkaConsumer;

/**
 * Consumer Kafka do serviço (container próprio): assina `proposta.avaliada`
 * com o consumer group proposta-service e atualiza o status do aggregate via
 * Use Case — o consumer é só um Driving Adapter, como o controller HTTP.
 */
class ConsumirPropostaAvaliada extends Command
{
    protected $signature = 'kafka:consumir-avaliadas';

    protected $description = 'Consome proposta.avaliada e atualiza o status das propostas';

    public function handle(RegistrarResultadoAvaliacao $useCase): int
    {
        $conf = new Conf();
        $conf->set('bootstrap.servers', env('KAFKA_BOOTSTRAP', 'kafka:9092'));
        $conf->set('group.id', 'proposta-service');
        $conf->set('auto.offset.reset', 'earliest');

        $consumer = new KafkaConsumer($conf);
        $consumer->subscribe(['proposta.avaliada']);
        $this->info('consumindo proposta.avaliada (group=proposta-service)');

        while (true) {
            $msg = $consumer->consume(5000);
            if ($msg->err === RD_KAFKA_RESP_ERR__TIMED_OUT || $msg->err === RD_KAFKA_RESP_ERR__PARTITION_EOF) {
                continue;
            }
            if ($msg->err) {
                $this->error('kafka: '.$msg->errstr());
                continue;
            }

            $evento = json_decode($msg->payload, true);
            try {
                $useCase->executar(
                    propostaId: $evento['proposta_id'],
                    resultado: $evento['resultado'],
                    parecer: (string) ($evento['parecer'] ?? ''),
                );
                $this->info("proposta {$evento['proposta_id']} -> {$evento['resultado']}");
            } catch (\Throwable $e) {
                // Loga e segue: num sistema real, dead letter / retry com backoff.
                $this->error("falha ao aplicar proposta.avaliada: {$e->getMessage()}");
            }
        }
    }
}
