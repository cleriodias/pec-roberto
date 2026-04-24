<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tb1_produto') || ! $this->indexExists('tb1_produto', 'tb1_produto_nome_fulltext')) {
            return;
        }

        DB::statement('ALTER TABLE tb1_produto DROP INDEX tb1_produto_nome_fulltext');
    }

    public function down(): void
    {
        if (! Schema::hasTable('tb1_produto') || $this->indexExists('tb1_produto', 'tb1_produto_nome_fulltext')) {
            return;
        }

        DB::statement('ALTER TABLE tb1_produto ADD FULLTEXT INDEX tb1_produto_nome_fulltext (tb1_nome)');
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
