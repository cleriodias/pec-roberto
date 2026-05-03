<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb1_produto', function (Blueprint $table) {
            $table->string('tb1_cff_nt', 20)->nullable()->after('tb1_cclasstrib');
        });
    }

    public function down(): void
    {
        Schema::table('tb1_produto', function (Blueprint $table) {
            $table->dropColumn('tb1_cff_nt');
        });
    }
};
