<?php

namespace App\Console\Commands;

use App\Infrastructure\Kafka\KafkaProducer;
use App\Infrastructure\Persistence\OutboxModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Relay do Outbox Pattern: roda em container próprio, varre a
 * tabela outbox e publica no Kafka o que ainda não foi publicado.
 *
 * FOR UPDATE SKIP LOCKED permite escalar o relay horizontalmente sem publicar
 * a mesma linha duas vezes. Entrega garantida é "ao menos uma vez" — os
 * consumidores precisam ser idempotentes (e são: upserts por chave).
 */
class OutboxRelay extends Command
{
    protected $signature = 'outbox:relay {--intervalo=1 : segundos entre varreduras}';

    protected $description = 'Publica no Kafka os eventos pendentes da tabela outbox';

    public function handle(KafkaProducer $kafka): int
    {
        $intervalo = max(1, (int) $this->option('intervalo'));
        $this->info('outbox:relay iniciado');

        while (true) {
            $publicadas = DB::transaction(function () use ($kafka) {
                $linhas = OutboxModel::whereNull('publicado_em')
                    ->orderBy('id')
                    ->limit(100)
                    ->lock('for update skip locked')
                    ->get();

                foreach ($linhas as $linha) {
                    $kafka->publicar($linha->topico, $linha->chave, $linha->payload);
                }
                if ($linhas->isNotEmpty()) {
                    $kafka->flush();
                    OutboxModel::whereIn('id', $linhas->pluck('id'))
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
