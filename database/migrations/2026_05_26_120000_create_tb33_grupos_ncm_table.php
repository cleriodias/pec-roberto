<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb33_grupos_ncm', function (Blueprint $table) {
            $table->bigIncrements('tb33_id');
            $table->string('tb33_codigo', 30)->nullable()->unique();
            $table->string('tb33_nome', 120);
            $table->text('tb33_descricao')->nullable();
            $table->string('tb33_ncm', 8);
            $table->string('tb33_cest', 7)->nullable();
            $table->string('tb33_cclass_trib', 6)->nullable();
            $table->boolean('tb33_ativo')->default(true);
            $table->text('tb33_observacao_fiscal')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb33_grupos_ncm');
    }
};
