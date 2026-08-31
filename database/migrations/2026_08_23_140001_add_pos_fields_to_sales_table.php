<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('discount', 18, 6)->default(0)->after('tax');
            $table->decimal('cash_received', 18, 6)->nullable()->after('cost_total');
            $table->decimal('change_given', 18, 6)->nullable()->after('cash_received');
            $table->boolean('is_pos')->default(false)->after('change_given');
            $table->index(['is_pos', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['is_pos', 'created_at']);
            $table->dropColumn(['discount', 'cash_received', 'change_given', 'is_pos']);
        });
    }
};