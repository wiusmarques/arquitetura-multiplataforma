<?php

namespace App\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

class OutboxModel extends Model
{
    protected $table = 'outbox';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'payload' => 'array',
    ];
}
