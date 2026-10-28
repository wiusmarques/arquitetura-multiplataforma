<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox', function (Blueprint $table) {
            $table->id();
            $table->string('topico', 100);
            $table->string('chave', 100);
            $table->jsonb('payload');
            $table->timestampTz('criado_em');
            $table->timestampTz('publicado_em')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox');
    }
};
