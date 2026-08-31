<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            if (! $this->indexExists('activity_log', 'activity_log_created_at_index')) {
                $table->index('created_at', 'activity_log_created_at_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            if ($this->indexExists('activity_log', 'activity_log_created_at_index')) {
                $table->dropIndex('activity_log_created_at_index');
            }
        });
    }

    private function indexExists(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))->pluck('name')->contains($name);
    }
};