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
        Schema::create('kitchen_raw_material_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 110)->unique();
            $table->timestamps();
        });

        Schema::create('kitchen_raw_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('kitchen_raw_material_categories')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('sku', 80)->unique();
            $table->string('unit', 40);
            $table->decimal('quantity', 14, 4)->default(0);
            $table->decimal('alert_level', 14, 4)->default(0);
            $table->decimal('cost_per_unit', 14, 2)->default(0);
            $table->decimal('average_cost', 14, 2)->default(0);
            $table->decimal('last_cost', 14, 2)->default(0);
            $table->string('supplier', 160)->nullable();
            $table->string('batch_no', 80)->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('active')->default(true);
            $table->softDeletes();
            $table->timestamps();
            $table->index(['category_id', 'name']);
            $table->index(['quantity', 'alert_level']);
        });

        Schema::create('kitchen_raw_material_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_material_id')->constrained('kitchen_raw_materials')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 24);
            $table->decimal('quantity_delta', 14, 4);
            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->string('reason', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['raw_material_id', 'created_at']);
        });

        Schema::create('kitchen_recipes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('code', 80)->unique();
            $table->string('category', 100);
            $table->string('type', 40)->default('Menu Item');
            $table->decimal('output_quantity', 14, 4)->default(1);
            $table->string('output_unit', 40)->default('serving');
            $table->unsignedSmallInteger('prep_time')->default(0);
            $table->unsignedSmallInteger('cook_time')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->text('instructions')->nullable();
            $table->boolean('active')->default(true);
            $table->softDeletes();
            $table->timestamps();
            $table->index(['category', 'name']);
        });

        Schema::create('kitchen_recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('kitchen_recipes')->cascadeOnDelete();
            $table->foreignId('raw_material_id')->constrained('kitchen_raw_materials')->restrictOnDelete();
            $table->decimal('quantity', 14, 4);
            $table->string('unit', 40);
            $table->decimal('waste_percent', 6, 2)->default(0);
            $table->timestamps();
            $table->unique(['recipe_id', 'raw_material_id']);
        });

        Schema::create('kitchen_food_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('category', 100);
            $table->decimal('price', 14, 2)->default(0);
            $table->longText('image')->nullable();
            $table->text('instructions')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('available')->default(true);
            $table->softDeletes();
            $table->timestamps();
            $table->index(['category', 'name']);
        });

        Schema::create('kitchen_food_item_recipes', function (Blueprint $table) {
            $table->foreignId('food_item_id')->constrained('kitchen_food_items')->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained('kitchen_recipes')->cascadeOnDelete();
            $table->primary(['food_item_id', 'recipe_id']);
        });

        $now = now();
        foreach (config('kitchen.raw_material_categories', []) as $name) {
            DB::table('kitchen_raw_material_categories')->insertOrIgnore([
                'name' => $name,
                'slug' => Str::slug($name),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_food_item_recipes');
        Schema::dropIfExists('kitchen_food_items');
        Schema::dropIfExists('kitchen_recipe_ingredients');
        Schema::dropIfExists('kitchen_recipes');
        Schema::dropIfExists('kitchen_raw_material_movements');
        Schema::dropIfExists('kitchen_raw_materials');
        Schema::dropIfExists('kitchen_raw_material_categories');
    }
};
