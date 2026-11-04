<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('number', 24)->unique();
            $table->string('type', 60);
            $table->string('floor', 60)->nullable();
            $table->decimal('rate', 14, 2)->default(0);
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->string('amenities', 500)->nullable();
            $table->string('status', 24)->default('available');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('room_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name', 120);
            $table->string('guest_phone', 60);
            $table->date('check_in_date');
            $table->unsignedSmallInteger('nights')->default(1);
            $table->unsignedSmallInteger('guests')->default(1);
            $table->decimal('rate', 14, 2)->default(0);
            $table->decimal('deposit', 14, 2)->default(0);
            $table->string('status', 24)->default('confirmed');
            $table->timestamps();
            $table->index(['room_id', 'status']);
            $table->index(['check_in_date', 'status']);
        });

        Schema::create('room_stays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_reservation_id')->nullable()->constrained('room_reservations')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name', 120);
            $table->string('guest_phone', 60);
            $table->unsignedSmallInteger('guests')->default(1);
            $table->decimal('rate', 14, 2)->default(0);
            $table->dateTime('check_in_at');
            $table->dateTime('expected_checkout_at');
            $table->dateTime('checked_out_at')->nullable();
            $table->string('status', 24)->default('in_house');
            $table->timestamps();
            $table->index(['room_id', 'status']);
            $table->index(['status', 'checked_out_at']);
        });

        Schema::create('room_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_stay_id')->constrained('room_stays')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 180);
            $table->decimal('amount', 14, 2);
            $table->timestamps();
        });

        Schema::create('room_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_stay_id')->nullable()->constrained('room_stays')->nullOnDelete();
            $table->foreignId('room_reservation_id')->nullable()->constrained('room_reservations')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name', 120);
            $table->decimal('amount', 14, 2);
            $table->string('payment_method', 120)->default('Cash');
            $table->date('paid_on');
            $table->string('note', 180)->nullable();
            $table->timestamps();
            $table->index(['paid_on', 'room_id']);
        });

        Schema::create('room_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 180);
            $table->string('category', 60)->default('Supplies');
            $table->decimal('amount', 14, 2);
            $table->date('expense_date');
            $table->timestamps();
            $table->index(['expense_date', 'room_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_expenses');
        Schema::dropIfExists('room_payments');
        Schema::dropIfExists('room_charges');
        Schema::dropIfExists('room_stays');
        Schema::dropIfExists('room_reservations');
        Schema::dropIfExists('rooms');
    }
};
