<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Composite indexes for the queries that power the dashboard, sales,
     * reports, staff and POS live feeds. Each is checked first so the
     * migration is safe to run against an already-tuned database.
     */
    public function up(): void
    {
        $this->addIndex('pos_orders', ['counter_id', 'created_at']);
        $this->addIndex('pos_orders', ['created_at']);
        $this->addIndex('pos_orders', ['user_id', 'created_at']);

        $this->addIndex('inventory_movements', ['type', 'created_at']);
        $this->addIndex('kitchen_raw_material_movements', ['type', 'created_at']);

        $this->addIndex('users', ['role', 'status']);
        $this->addIndex('users', ['name']);

        $this->addIndex('kitchen_food_items', ['available', 'name']);
        $this->addIndex('kitchen_recipes', ['active', 'name']);
        $this->addIndex('inventory_products', ['deleted_at', 'category_id']);

        $this->addIndex('rooms', ['type']);
        $this->addIndex('room_reservations', ['status', 'check_in_date']);
    }

    public function down(): void
    {
        $this->dropIndex('pos_orders', ['counter_id', 'created_at']);
        $this->dropIndex('pos_orders', ['created_at']);
        $this->dropIndex('pos_orders', ['user_id', 'created_at']);

        $this->dropIndex('inventory_movements', ['type', 'created_at']);
        $this->dropIndex('kitchen_raw_material_movements', ['type', 'created_at']);

        $this->dropIndex('users', ['role', 'status']);
        $this->dropIndex('users', ['name']);

        $this->dropIndex('kitchen_food_items', ['available', 'name']);
        $this->dropIndex('kitchen_recipes', ['active', 'name']);
        $this->dropIndex('inventory_products', ['deleted_at', 'category_id']);

        $this->dropIndex('rooms', ['type']);
        $this->dropIndex('room_reservations', ['status', 'check_in_date']);
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function addIndex(string $table, array $columns): void
    {
        $name = $this->indexName($table, $columns);

        if (! Schema::hasTable($table) || Schema::hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function dropIndex(string $table, array $columns): void
    {
        $name = $this->indexName($table, $columns);

        if (! Schema::hasTable($table) || ! Schema::hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function indexName(string $table, array $columns): string
    {
        return $table.'_'.implode('_', $columns).'_index';
    }
};
