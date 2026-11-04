<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 110)->unique();
            $table->timestamps();
        });

        Schema::create('inventory_counters', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 110)->unique();
            $table->timestamps();
        });

        Schema::create('inventory_units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->timestamps();
        });

        Schema::create('inventory_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('inventory_categories')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('sku', 80)->nullable()->unique();
            $table->string('unit', 40);
            $table->decimal('buy_price', 14, 2)->default(0);
            $table->decimal('sell_price', 14, 2)->default(0);
            $table->string('supplier', 160)->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['category_id', 'name']);
        });

        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->foreignId('counter_id')->constrained('inventory_counters')->restrictOnDelete();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->decimal('reorder_level', 14, 3)->default(0);
            $table->date('expiry_date')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'counter_id']);
            $table->index(['counter_id', 'quantity', 'reorder_level']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('inventory_products')->restrictOnDelete();
            $table->foreignId('counter_id')->nullable()->constrained('inventory_counters')->nullOnDelete();
            $table->foreignId('from_counter_id')->nullable()->constrained('inventory_counters')->nullOnDelete();
            $table->foreignId('to_counter_id')->nullable()->constrained('inventory_counters')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 24);
            $table->decimal('quantity_delta', 14, 3);
            $table->string('reason', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'created_at']);
            $table->index(['counter_id', 'created_at']);
        });

        $now = now();
        foreach (config('inventory.categories', []) as $category) {
            DB::table('inventory_categories')->insertOrIgnore([
                'name' => $category['label'],
                'slug' => $category['id'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (config('inventory.counters', []) as $counter) {
            $slug = Str::slug($counter);
            DB::table('inventory_counters')->insertOrIgnore([
                'name' => $counter,
                'slug' => $slug,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (config('inventory.units', []) as $unit) {
            DB::table('inventory_units')->insertOrIgnore([
                'name' => $unit,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_stocks');
        Schema::dropIfExists('inventory_products');
        Schema::dropIfExists('inventory_units');
        Schema::dropIfExists('inventory_counters');
        Schema::dropIfExists('inventory_categories');
    }
};
