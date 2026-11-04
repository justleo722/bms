<?php

use App\Models\Expense;
use App\Models\InventoryCategory;
use App\Models\InventoryCounter;
use App\Models\InventoryMovement;
use App\Models\InventoryProduct;
use App\Models\KitchenRawMaterial;
use App\Models\KitchenRawMaterialCategory;
use App\Models\KitchenRawMaterialMovement;
use App\Models\PosOrder;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['name' => 'Report Admin']));
});

it('builds every report from persisted records using the current month by default', function () {
    $counter = InventoryCounter::query()->where('name', 'Main Counter')->firstOrFail();
    $category = InventoryCategory::query()->firstOrFail();

    $product = InventoryProduct::query()->create([
        'category_id' => $category->id,
        'name' => 'Report Beverage',
        'unit' => 'Bottle',
        'buy_price' => 1000,
        'sell_price' => 5000,
        'supplier' => 'Report Supplier',
    ]);

    $order = PosOrder::query()->create([
        'docket_number' => 'REPORT-001',
        'user_id' => auth()->id(),
        'counter_id' => $counter->id,
        'payment_method' => 'Cash',
        'status' => 'paid',
        'subtotal' => 10000,
        'total' => 10000,
    ]);
    $order->items()->create([
        'product_type' => 'inventory',
        'product_id' => $product->id,
        'product_name' => 'Report Beverage',
        'quantity' => 2,
        'unit_price' => 5000,
        'line_total' => 10000,
    ]);

    InventoryMovement::query()->create([
        'product_id' => $product->id,
        'counter_id' => $counter->id,
        'user_id' => auth()->id(),
        'type' => 'restock',
        'quantity_delta' => 3,
        'reason' => 'Restock',
    ]);

    Expense::query()->create([
        'expense_number' => 'EXP-REPORT-001',
        'user_id' => auth()->id(),
        'counter_id' => $counter->id,
        'category' => 'utilities',
        'description' => 'Report test expense',
        'amount' => 1500,
        'expense_date' => now()->toDateString(),
        'status' => 'cleared',
    ]);

    $materialCategory = KitchenRawMaterialCategory::query()->firstOrFail();
    $material = KitchenRawMaterial::query()->create([
        'category_id' => $materialCategory->id,
        'name' => 'Report Tomatoes',
        'sku' => 'REPORT-TOM-001',
        'unit' => 'Kg',
        'quantity' => 10,
        'average_cost' => 1500,
    ]);
    KitchenRawMaterialMovement::query()->create([
        'raw_material_id' => $material->id,
        'user_id' => auth()->id(),
        'type' => 'adjustment',
        'quantity_delta' => -2,
        'unit_cost' => 1500,
        'reason' => 'Damaged stock',
    ]);

    $response = $this->get('/reports');

    $response->assertOk()
        ->assertSee('Main Counter')
        ->assertDontSee('Tips');

    $response->assertViewHas('reports', function (array $reports) {
        expect($reports['sales']['summary'][0]['value'])->toBe(10000.0)
            ->and($reports['sales']['from'])->toBe(now()->startOfMonth()->toDateString())
            ->and($reports['purchases']['sections'][0]['rows'][0][2])->toBe('Report Admin')
            ->and($reports['kitchen']['summary'][3]['value'])->toBe(3000.0)
            ->and($reports['financial']['summary'][3]['value'])->toBe(6500.0);

        $staffColumns = array_column($reports['staff']['sections'][0]['columns'], 'label');

        expect($staffColumns)->not->toContain('Tips');

        return true;
    });
});

it('filters sales and purchases by counter', function () {
    $main = InventoryCounter::query()->where('name', 'Main Counter')->firstOrFail();
    $pool = InventoryCounter::query()->where('name', 'Pool Bar')->firstOrFail();
    $category = InventoryCategory::query()->firstOrFail();

    $product = InventoryProduct::query()->create([
        'category_id' => $category->id,
        'name' => 'Counter Test Item',
        'unit' => 'Bottle',
        'buy_price' => 1000,
        'sell_price' => 5000,
    ]);

    foreach ([[$main, 'REPORT-MAIN', 5000], [$pool, 'REPORT-POOL', 9000]] as [$counter, $docket, $total]) {
        $order = PosOrder::query()->create([
            'docket_number' => $docket,
            'user_id' => auth()->id(),
            'counter_id' => $counter->id,
            'payment_method' => 'Cash',
            'status' => 'paid',
            'subtotal' => $total,
            'total' => $total,
        ]);
        $order->items()->create([
            'product_type' => 'inventory',
            'product_id' => $product->id,
            'product_name' => 'Counter Test Item',
            'quantity' => 1,
            'unit_price' => $total,
            'line_total' => $total,
        ]);
    }

    $this->get('/reports?'.http_build_query(['counter' => 'Pool Bar']))
        ->assertOk()
        ->assertViewHas('reports', function (array $reports) {
            expect($reports['sales']['summary'][0]['value'])->toBe(9000.0)
                ->and($reports['sales']['summary'][1]['value'])->toBe(1);

            return true;
        });
});

it('renders the printable report view', function () {
    $this->get('/reports/export/pdf?'.http_build_query(['type' => 'sales']))
        ->assertOk()
        ->assertSee('Print / Save as PDF')
        ->assertSee('Sales');
});

it('exports a report as an excel workbook', function () {
    $response = $this->get('/reports/export/excel?'.http_build_query([
        'type' => 'staff',
        'from' => now()->startOfMonth()->toDateString(),
        'to' => now()->toDateString(),
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});
