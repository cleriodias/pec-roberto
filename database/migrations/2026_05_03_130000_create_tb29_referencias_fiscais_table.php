<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb29_referencias_fiscais', function (Blueprint $table) {
            $table->bigIncrements('tb29_id');
            $table->string('tb29_descricao', 120);
            $table->string('tb29_ncm', 8);
            $table->string('tb29_cfop', 4);
            $table->string('tb29_csosn', 4);
            $table->string('tb29_cst', 3);
            $table->string('tb29_cst_ibscbs', 3);
            $table->string('tb29_cclasstrib', 6);
            $table->decimal('tb29_aliquota_ibs_uf', 6, 4);
            $table->decimal('tb29_aliquota_ibs_mun', 6, 4);
            $table->decimal('tb29_aliquota_cbs', 6, 4);
            $table->decimal('tb29_aliquota_is', 6, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb29_referencias_fiscais');
    }
};
