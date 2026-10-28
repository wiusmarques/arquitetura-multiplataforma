<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editais', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('titulo', 200);
            $table->text('descricao')->nullable();
            $table->decimal('valor_total', 14, 2);
            $table->decimal('valor_por_candidato', 14, 2);
            $table->integer('numero_candidatos');
            $table->decimal('nota_maxima', 5, 2);
            $table->date('data_abertura');
            $table->date('data_encerramento');
            $table->string('status', 30)->default('rascunho'); // rascunho | publicado | resultado_publicado
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editais');
    }
};
