<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb30_categorias_fiscais', function (Blueprint $table) {
            $table->bigIncrements('tb30_id');
            $table->string('tb30_codigo', 30)->nullable()->unique();
            $table->string('tb30_nome', 120);
            $table->text('tb30_descricao')->nullable();
            $table->string('tb30_origem_mercadoria', 30);
            $table->boolean('tb30_ativo')->default(true);
            $table->date('tb30_data_inicio_vigencia')->nullable();
            $table->date('tb30_data_fim_vigencia')->nullable();
            $table->string('tb30_ncm_padrao', 8)->nullable();
            $table->string('tb30_cest', 7)->nullable();
            $table->string('tb30_cclass_trib', 6)->nullable();
            $table->string('tb30_cst_ibs', 3)->nullable();
            $table->string('tb30_cst_cbs', 3)->nullable();
            $table->decimal('tb30_aliquota_ibs_uf', 6, 4)->nullable();
            $table->decimal('tb30_aliquota_ibs_municipio', 6, 4)->nullable();
            $table->decimal('tb30_aliquota_cbs', 6, 4)->nullable();
            $table->decimal('tb30_aliquota_is', 6, 4)->nullable();
            $table->string('tb30_cfop_venda_interna', 4)->nullable();
            $table->string('tb30_cfop_venda_interestadual', 4)->nullable();
            $table->string('tb30_cfop_consumo_local', 4)->nullable();
            $table->string('tb30_cfop_entrega', 4)->nullable();
            $table->string('tb30_csosn', 4)->nullable();
            $table->string('tb30_cst_icms', 3)->nullable();
            $table->string('tb30_cst_pis', 3)->nullable();
            $table->string('tb30_cst_cofins', 3)->nullable();
            $table->decimal('tb30_aliquota_icms', 5, 2)->nullable();
            $table->decimal('tb30_aliquota_pis', 6, 4)->nullable();
            $table->decimal('tb30_aliquota_cofins', 6, 4)->nullable();
            $table->string('tb30_regra_icms', 120)->nullable();
            $table->string('tb30_natureza_receita', 60)->nullable();
            $table->boolean('tb30_aplica_balcao')->default(true);
            $table->boolean('tb30_aplica_delivery')->default(true);
            $table->boolean('tb30_aplica_consumo_local')->default(true);
            $table->boolean('tb30_permite_excecao_produto')->default(true);
            $table->text('tb30_observacao_fiscal')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb30_categorias_fiscais');
    }
};
