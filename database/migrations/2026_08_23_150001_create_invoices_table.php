<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('sale_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('third_party_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('currency_id')->nullable()->constrained()->nullOnDelete();
            $table->date('issue_date')->index();
            $table->decimal('subtotal', 18, 6)->default(0);
            $table->decimal('discount', 18, 6)->default(0);
            $table->decimal('tax', 18, 6)->default(0);
            $table->decimal('total', 18, 6)->default(0);
            $table->string('status')->default('issued')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE invoices MODIFY COLUMN status ENUM('issued', 'cancelled') NOT NULL DEFAULT 'issued'");
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};