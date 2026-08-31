<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->date('date')->index();
            $table->text('description');
            $table->string('status')->default('draft')->index();
            $table->decimal('total_debit', 18, 6)->default(0);
            $table->decimal('total_credit', 18, 6)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE journal_entries MODIFY COLUMN status ENUM('draft', 'posted') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};