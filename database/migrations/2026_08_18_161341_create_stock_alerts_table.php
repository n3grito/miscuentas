<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->decimal('current_qty', 15, 4);
            $table->decimal('min_stock', 15, 4)->nullable();
            $table->decimal('max_stock', 15, 4)->nullable();
            $table->enum('level', ['below_min', 'over_max']);
            $table->enum('status', ['open', 'resolved'])->default('open')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_alerts');
    }
};