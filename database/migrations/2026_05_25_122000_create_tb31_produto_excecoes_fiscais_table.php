<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb31_produto_excecoes_fiscais', function (Blueprint $table) {
            $table->bigIncrements('tb31_id');
            $table->unsignedBigInteger('tb1_id');
            $table->boolean('tb31_ativo')->default(true);
            $table->string('tb31_motivo_excecao', 255)->nullable();
            $table->date('tb31_data_inicio_vigencia')->nullable();
            $table->date('tb31_data_fim_vigencia')->nullable();
            $table->string('tb31_ncm', 8)->nullable();
            $table->string('tb31_cest', 7)->nullable();
            $table->string('tb31_cclass_trib', 6)->nullable();
            $table->string('tb31_cst_ibs', 3)->nullable();
            $table->string('tb31_cst_cbs', 3)->nullable();
            $table->decimal('tb31_aliquota_ibs_uf', 6, 4)->nullable();
            $table->decimal('tb31_aliquota_ibs_municipio', 6, 4)->nullable();
            $table->decimal('tb31_aliquota_cbs', 6, 4)->nullable();
            $table->decimal('tb31_aliquota_is', 6, 4)->nullable();
            $table->string('tb31_cfop_venda_interna', 4)->nullable();
            $table->string('tb31_cfop_venda_interestadual', 4)->nullable();
            $table->string('tb31_cfop_consumo_local', 4)->nullable();
            $table->string('tb31_cfop_entrega', 4)->nullable();
            $table->string('tb31_csosn', 4)->nullable();
            $table->string('tb31_cst_icms', 3)->nullable();
            $table->string('tb31_cst_pis', 3)->nullable();
            $table->string('tb31_cst_cofins', 3)->nullable();
            $table->decimal('tb31_aliquota_icms', 5, 2)->nullable();
            $table->decimal('tb31_aliquota_pis', 6, 4)->nullable();
            $table->decimal('tb31_aliquota_cofins', 6, 4)->nullable();
            $table->text('tb31_observacao_fiscal')->nullable();
            $table->timestamps();

            $table->foreign('tb1_id', 'tb31_excecao_produto_fk')
                ->references('tb1_id')
                ->on('tb1_produto')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb31_produto_excecoes_fiscais');
    }
};
