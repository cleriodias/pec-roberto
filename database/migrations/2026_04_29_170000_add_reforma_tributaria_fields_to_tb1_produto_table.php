<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb1_produto', function (Blueprint $table) {
            $table->string('tb1_cst_ibscbs', 3)->nullable()->after('tb1_aliquota_icms');
            $table->string('tb1_cclasstrib', 6)->nullable()->after('tb1_cst_ibscbs');
            $table->boolean('tb1_ind_doacao')->default(false)->after('tb1_cclasstrib');
            $table->decimal('tb1_aliquota_ibs_uf', 6, 4)->nullable()->after('tb1_ind_doacao');
            $table->decimal('tb1_aliquota_ibs_mun', 6, 4)->nullable()->after('tb1_aliquota_ibs_uf');
            $table->decimal('tb1_aliquota_cbs', 6, 4)->nullable()->after('tb1_aliquota_ibs_mun');
            $table->decimal('tb1_aliquota_is', 6, 4)->nullable()->after('tb1_aliquota_cbs');
        });
    }

    public function down(): void
    {
        Schema::table('tb1_produto', function (Blueprint $table) {
            $table->dropColumn([
                'tb1_cst_ibscbs',
                'tb1_cclasstrib',
                'tb1_ind_doacao',
                'tb1_aliquota_ibs_uf',
                'tb1_aliquota_ibs_mun',
                'tb1_aliquota_cbs',
                'tb1_aliquota_is',
            ]);
        });
    }
};
