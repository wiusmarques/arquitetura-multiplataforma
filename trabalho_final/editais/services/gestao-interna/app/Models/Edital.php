<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Monolito MVC padrão de propósito: gestão interna tem meia dúzia de usuários
 * e baixa volumetria — Clean Architecture aqui seria over-engineering.
 * O rigor arquitetural fica no proposta-service.
 */
class Edital extends Model
{
    use HasUuids;

    protected $table = 'editais';

    protected $guarded = [];

    protected $casts = [
        'valor_total' => 'decimal:2',
        'valor_por_candidato' => 'decimal:2',
        'numero_candidatos' => 'integer',
        'nota_maxima' => 'decimal:2',
        'data_abertura' => 'date:Y-m-d',
        'data_encerramento' => 'date:Y-m-d',
    ];

    /**
     * Encerramento é DERIVADO da vigência (não há ação manual de encerrar):
     * edital publicado cuja data de encerramento passou não aceita propostas.
     */
    public function situacao(): string
    {
        if ($this->status === 'publicado' && $this->data_encerramento->isPast()) {
            return 'encerrado';
        }

        return $this->status;
    }
}
