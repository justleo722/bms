<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_code_pools', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->timestamp('initial_notified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('registration_entry_codes', function (Blueprint $table) {
            $table->id();
            $table->char('code_hash', 64)->unique();
            $table->text('code_encrypted');
            $table->timestamp('consumed_at')->nullable()->index();
            $table->foreignId('consumed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('registration_code_pools')->insert([
            'id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_entry_codes');
        Schema::dropIfExists('registration_code_pools');
    }
};
