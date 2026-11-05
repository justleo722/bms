<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The original demo table set that shipped with the POS.
     *
     * @var list<string>
     */
    private const LEGACY_DEMO_TABLES = [
        'Counter',
        'VIP 1', 'VIP 2', 'VIP 3',
        'Bar 1', 'Bar 2', 'Bar 3',
        'Patio 1', 'Patio 2',
        'Pool 1', 'Pool 2',
        'Lounge 1', 'Lounge 2',
        'Takeaway',
    ];

    public function up(): void
    {
        // New installations start with a simple, predictable set of tables.
        foreach (range(1, 10) as $number) {
            DB::table('pos_tables')->insertOrIgnore([
                'name' => 'T'.$number,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Remove the old demo tables only when they are not attached to an
        // order. This keeps real order history safe on existing installations.
        $legacyIds = DB::table('pos_tables')
            ->whereIn('name', self::LEGACY_DEMO_TABLES)
            ->pluck('id');

        foreach ($legacyIds as $id) {
            $inUse = DB::table('pos_orders')->where('table_id', $id)->exists();

            if (! $inUse) {
                DB::table('pos_tables')->where('id', $id)->delete();
            }
        }
    }

    public function down(): void
    {
        // Removed demo tables cannot be restored reliably, and the new
        // T1-T10 set is intentionally kept.
    }
};
