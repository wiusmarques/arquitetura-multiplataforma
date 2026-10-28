<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('propostas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('edital_id')->index();
            $table->string('proponente')->index();
            $table->string('titulo', 200);
            $table->text('descricao');
            $table->text('retorno_esperado');
            $table->date('data_inicio');
            $table->integer('meses_execucao');
            $table->string('status', 20)->default('submetida');
            $table->text('parecer')->nullable();
            // Preenchido pelo fluxo de pagamentos (consumer de resultado.publicado).
            $table->timestampTz('pago_em')->nullable();
            $table->timestampTz('submetida_em');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('propostas');
    }
};
