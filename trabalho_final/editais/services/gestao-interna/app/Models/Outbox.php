<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Outbox extends Model
{
    protected $table = 'outbox';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'payload' => 'array',
    ];
}
