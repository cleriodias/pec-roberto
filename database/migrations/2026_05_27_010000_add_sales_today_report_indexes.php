<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('expenses')) {
            $this->addIndexIfMissing(
                'expenses',
                'expenses_unit_date_idx',
                'ALTER TABLE expenses ADD INDEX expenses_unit_date_idx (unit_id, expense_date)'
            );
        }

        if (Schema::hasTable('tb2_unidade_user')) {
            $this->addIndexIfMissing(
                'tb2_unidade_user',
                'tb2_unidade_user_user_unit_idx',
                'ALTER TABLE tb2_unidade_user ADD INDEX tb2_unidade_user_user_unit_idx (user_id, tb2_id)'
            );
        }
    }

    public function down(): void
    {
        $this->dropIndexIfExists('tb2_unidade_user', 'tb2_unidade_user_user_unit_idx');
        $this->dropIndexIfExists('expenses', 'expenses_unit_date_idx');
    }

    private function addIndexIfMissing(string $table, string $index, string $statement): void
    {
        if (! $this->indexExists($table, $index)) {
            DB::statement($statement);
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (Schema::hasTable($table) && $this->indexExists($table, $index)) {
            DB::statement(sprintf('ALTER TABLE %s DROP INDEX %s', $table, $index));
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
