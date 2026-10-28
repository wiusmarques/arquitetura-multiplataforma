<?php

namespace App\Console\Commands;

use App\Models\Outbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RdKafka\Conf;
use RdKafka\Producer;

/**
 * Relay do Outbox do monolito (container próprio): publica edital.publicado e
 * resultado.publicado pendentes. FOR UPDATE SKIP LOCKED permite mais de um
 * relay sem duplicar publicação; entrega "ao menos uma vez" — consumidores
 * idempotentes.
 */
class OutboxRelay extends Command
{
    protected $signature = 'outbox:relay {--intervalo=1 : segundos entre varreduras}';

    protected $description = 'Publica no Kafka os eventos pendentes da tabela outbox';

    public function handle(): int
    {
        $conf = new Conf();
        $conf->set('bootstrap.servers', env('KAFKA_BOOTSTRAP', 'kafka:9092'));
        $producer = new Producer($conf);

        $intervalo = max(1, (int) $this->option('intervalo'));
        $this->info('outbox:relay iniciado');

        while (true) {
            $publicadas = DB::transaction(function () use ($producer) {
                $linhas = Outbox::whereNull('publicado_em')
                    ->orderBy('id')
                    ->limit(100)
                    ->lockForUpdate()
                    ->get();

                foreach ($linhas as $linha) {
                    $producer->newTopic($linha->topico)->produce(
                        RD_KAFKA_PARTITION_UA,
                        0,
                        json_encode($linha->payload, JSON_UNESCAPED_UNICODE),
                        $linha->chave,
                    );
                    $producer->poll(0);
                }
                if ($linhas->isNotEmpty()) {
                    if ($producer->flush(10000) > 0) {
                        throw new \RuntimeException('Kafka: mensagens não confirmadas no flush.');
                    }
                    Outbox::whereIn('id', $linhas->pluck('id'))
                        ->update(['publicado_em' => now()]);
                }

                return $linhas->count();
            });

            if ($publicadas > 0) {
                $this->info(now()->toDateTimeString()." — {$publicadas} evento(s) publicado(s)");
            }
            sleep($intervalo);
        }
    }
}
