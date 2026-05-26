<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb1_produto', function (Blueprint $table) {
            $table->unsignedBigInteger('tb33_grupo_ncm_id')->nullable()->after('tb30_categoria_fiscal_id');

            $table->foreign('tb33_grupo_ncm_id', 'tb1_produto_tb33_grupo_ncm_fk')
                ->references('tb33_id')
                ->on('tb33_grupos_ncm')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tb1_produto', function (Blueprint $table) {
            $table->dropForeign('tb1_produto_tb33_grupo_ncm_fk');
            $table->dropColumn('tb33_grupo_ncm_id');
        });
    }
};
