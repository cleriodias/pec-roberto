<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb33_grupos_ncm', function (Blueprint $table) {
            $table->string('tb33_ncm', 8)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tb33_grupos_ncm', function (Blueprint $table) {
            $table->string('tb33_ncm', 8)->nullable(false)->change();
        });
    }
};
