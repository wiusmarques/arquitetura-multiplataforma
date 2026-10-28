<?php

namespace App\Providers;

use App\Application\Ports\Editais;
use App\Application\Ports\Outbox;
use App\Domain\Proposta\PropostaRepository;
use App\Infrastructure\Http\HttpEditais;
use App\Infrastructure\Persistence\EloquentOutbox;
use App\Infrastructure\Persistence\EloquentPropostaRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Regra de Dependência (Clean Architecture): Domain e Application definem
     * as interfaces; a Infrastructure implementa. A amarração acontece aqui,
     * na borda do framework — o domínio nunca conhece Eloquent nem Kafka.
     */
    public function register(): void
    {
        $this->app->bind(PropostaRepository::class, EloquentPropostaRepository::class);
        $this->app->bind(Outbox::class, EloquentOutbox::class);
        $this->app->bind(Editais::class, HttpEditais::class);
    }

    public function boot(): void
    {
        //
    }
}
