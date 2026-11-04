<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->softDeletes();
            $table->timestamps();
        });

        foreach ([
            ['Cashier', 'cashier'],
            ['Bartender', 'bartender'],
            ['Manager', 'manager'],
            ['Waiter', 'waiter'],
            ['Kitchen', 'kitchen'],
            ['Security', 'security'],
        ] as [$name, $slug]) {
            DB::table('staff_roles')->insert([
                'name' => $name,
                'slug' => $slug,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('pin');
            $table->foreignId('counter_id')->nullable()->after('status')->constrained('inventory_counters')->nullOnDelete();
            $table->json('permissions')->nullable()->after('counter_id');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['counter_id']);
            $table->dropColumn(['status', 'counter_id', 'permissions', 'deleted_at']);
        });

        Schema::dropIfExists('staff_roles');
    }
};
