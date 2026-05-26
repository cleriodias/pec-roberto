<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb1_produto', function (Blueprint $table) {
            $table->unsignedBigInteger('tb30_categoria_fiscal_id')->nullable()->after('tb1_tipo');
            $table->string('tb1_ncm_proprio', 8)->nullable()->after('tb30_categoria_fiscal_id');
            $table->boolean('tb1_usa_excecao_fiscal')->default(false)->after('tb1_ncm_proprio');
            $table->unsignedBigInteger('tb1_responsavel_ultima_alteracao')->nullable()->after('tb1_usa_excecao_fiscal');

            $table->foreign('tb30_categoria_fiscal_id', 'tb1_produto_tb30_categoria_fk')
                ->references('tb30_id')
                ->on('tb30_categorias_fiscais')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tb1_produto', function (Blueprint $table) {
            $table->dropForeign('tb1_produto_tb30_categoria_fk');
            $table->dropColumn([
                'tb30_categoria_fiscal_id',
                'tb1_ncm_proprio',
                'tb1_usa_excecao_fiscal',
                'tb1_responsavel_ultima_alteracao',
            ]);
        });
    }
};
