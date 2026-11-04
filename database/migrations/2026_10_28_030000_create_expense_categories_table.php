<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 110)->unique();
            $table->softDeletes();
            $table->timestamps();
        });

        $now = now();
        foreach ([
            'rent' => 'Rent',
            'salaries' => 'Salaries',
            'utilities' => 'Utilities',
            'inventory' => 'Inventory',
            'marketing' => 'Marketing',
            'repairs' => 'Repairs',
            'supplies' => 'Supplies',
            'bad_wastage' => 'Bad/Wastage/Expired',
        ] as $slug => $name) {
            DB::table('expense_categories')->insert([
                'name' => $name,
                'slug' => $slug,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
