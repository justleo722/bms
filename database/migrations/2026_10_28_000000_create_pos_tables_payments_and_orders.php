<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_tables', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        Schema::create('pos_payment_modes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->boolean('enabled')->default(true);
            $table->boolean('requires_reference')->default(false);
            $table->string('reference_label', 120)->nullable();
            $table->string('icon', 20)->default('💵');
            $table->timestamps();
        });

        Schema::create('pos_orders', function (Blueprint $table) {
            $table->id();
            $table->string('docket_number', 32)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('counter_id')->constrained('inventory_counters')->restrictOnDelete();
            $table->foreignId('table_id')->nullable()->constrained('pos_tables')->nullOnDelete();
            $table->foreignId('payment_mode_id')->nullable()->constrained('pos_payment_modes')->nullOnDelete();
            $table->string('payment_method', 120)->nullable();
            $table->string('payment_reference', 160)->nullable();
            $table->string('status', 24)->default('paid');
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 14, 2);
            $table->decimal('total', 14, 2);
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('pos_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('pos_orders')->cascadeOnDelete();
            $table->string('product_type', 24);
            $table->unsignedBigInteger('product_id');
            $table->string('product_name', 160);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
            $table->index(['product_type', 'product_id']);
        });

        foreach ([
            'Counter', 'T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'T8',
            'VIP 1', 'VIP 2', 'VIP 3', 'Bar 1', 'Bar 2', 'Bar 3',
            'Patio 1', 'Patio 2', 'Pool 1', 'Pool 2', 'Lounge 1', 'Lounge 2', 'Takeaway',
        ] as $tableName) {
            DB::table('pos_tables')->insertOrIgnore([
                'name' => $tableName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ([
            ['Cash', true, false, null, '💵'],
            ['Card (Visa/Mastercard)', true, true, 'Approval code', '💳'],
            ['Mobile Money (M-Pesa, Tigo, Airtel)', true, true, 'Transaction / phone no.', '📱'],
            ['Bank Transfer', false, true, 'Reference / slip no.', '🏦'],
            ['Credit (House Account)', false, false, null, '🧾'],
        ] as [$name, $enabled, $requiresReference, $referenceLabel, $icon]) {
            DB::table('pos_payment_modes')->insertOrIgnore([
                'name' => $name,
                'enabled' => $enabled,
                'requires_reference' => $requiresReference,
                'reference_label' => $referenceLabel,
                'icon' => $icon,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_order_items');
        Schema::dropIfExists('pos_orders');
        Schema::dropIfExists('pos_payment_modes');
        Schema::dropIfExists('pos_tables');
    }
};
