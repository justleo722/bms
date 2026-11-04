<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number', 32)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('counter_id')->nullable()->constrained('inventory_counters')->nullOnDelete();
            $table->string('category', 40);
            $table->string('description', 180);
            $table->decimal('amount', 14, 2);
            $table->string('payment_method', 120)->nullable();
            $table->date('expense_date');
            $table->string('status', 24)->default('cleared');
            $table->boolean('is_recurring')->default(false);
            $table->string('repeat_interval', 24)->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['expense_date', 'counter_id']);
            $table->index(['category', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
