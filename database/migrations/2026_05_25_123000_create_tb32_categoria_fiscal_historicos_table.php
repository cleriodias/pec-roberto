<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb32_categoria_fiscal_historicos', function (Blueprint $table) {
            $table->bigIncrements('tb32_id');
            $table->unsignedBigInteger('tb30_categoria_fiscal_id')->nullable();
            $table->unsignedBigInteger('tb1_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('tb32_acao', 60);
            $table->string('tb32_campo', 120)->nullable();
            $table->text('tb32_valor_anterior')->nullable();
            $table->text('tb32_valor_novo')->nullable();
            $table->unsignedInteger('tb32_registros_afetados')->default(1);
            $table->timestamps();

            $table->index(['tb30_categoria_fiscal_id', 'tb1_id'], 'tb32_categoria_produto_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb32_categoria_fiscal_historicos');
    }
};
