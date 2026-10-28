<?php

namespace App\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * Detalhe de persistência (Frameworks & Drivers): o Model do Eloquent fica
 * confinado na Infrastructure — a entidade de domínio (App\Domain) nunca o vê.
 */
class PropostaModel extends Model
{
    protected $table = 'propostas';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public $timestamps = false;
}
