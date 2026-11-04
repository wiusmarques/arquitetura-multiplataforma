<?php

namespace App\Console\Commands;

use App\Application\PagarProposta;
use App\Domain\Proposta\PropostaRepository;
use App\Domain\Proposta\StatusProposta;
use Illuminate\Console\Command;
use RdKafka\Conf;
use RdKafka\KafkaConsumer;

/**
 * Pagamentos em massa: consome `resultado.publicado` (consumer group próprio)
 * e dispara o pagamento de cada proposta aprovada do edital. É a carga em
 * rajada que justifica o desenho assíncrono: a publicação do resultado
 * responde na hora e o lote é processado aqui, no ritmo do gateway.
 *
 * Falha do gateway em uma proposta não interrompe o lote: fica registrada
 * (pagamento_falhou_em) e a administração pode ressolicitar.
 */
class ProcessarPagamentos extends Command
{
    protected $signature = 'kafka:processar-pagamentos';

    protected $description = 'Consome resultado.publicado e paga as propostas aprovadas do edital';

    public function handle(PropostaRepository $propostas, PagarProposta $pagar): int
    {
        $conf = new Conf();
        $conf->set('bootstrap.servers', env('KAFKA_BOOTSTRAP', 'kafka:9092'));
        $conf->set('group.id', 'proposta-pagamentos');
        $conf->set('auto.offset.reset', 'earliest');

        $consumer = new KafkaConsumer($conf);
        $consumer->subscribe(['resultado.publicado']);
        $this->info('consumindo resultado.publicado (group=proposta-pagamentos)');

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
            $editalId = $evento['edital_id'] ?? null;
            if ($editalId === null) {
                continue;
            }

            // Idempotente por construção: só aprovadas entram, e quem já foi
            // paga sai do filtro — reprocessar o evento não paga duas vezes.
            $aprovadas = array_filter(
                $propostas->porEdital($editalId),
                fn ($p) => $p->status() === StatusProposta::Aprovada,
            );
            $this->info("resultado.publicado {$editalId}: ".count($aprovadas).' pagamento(s) a executar');

            foreach ($aprovadas as $proposta) {
                try {
                    $resultado = $pagar->executar($proposta->id);
                    $this->info("  {$proposta->id}: ".($resultado->status() === StatusProposta::Paga
                        ? 'pago' : 'falhou — aguardando nova solicitação'));
                } catch (\Throwable $e) {
                    $this->error("  {$proposta->id}: {$e->getMessage()}");
                }
            }
        }
    }
}
