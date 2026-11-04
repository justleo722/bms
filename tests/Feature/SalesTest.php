<?php

use App\Models\InventoryCounter;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\InventoryCategory;
use App\Models\InventoryProduct;
use App\Models\KitchenFoodItem;
use App\Models\KitchenRawMaterial;
use App\Models\KitchenRawMaterialCategory;
use App\Models\KitchenRawMaterialMovement;
use App\Models\KitchenRecipe;
use App\Models\PosOrder;
use App\Models\PosTable;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('shows persisted POS orders in sales with today as the default date filter', function () {
    $counter = InventoryCounter::query()->firstOrFail();
    $table = PosTable::query()->firstOrFail();
    $category = InventoryCategory::query()->firstOrFail();
    $product = InventoryProduct::query()->create([
        'category_id' => $category->id,
        'name' => 'Database Beverage',
        'unit' => 'Bottle',
        'buy_price' => 1000,
        'sell_price' => 6250,
    ]);
    $todayOrder = PosOrder::query()->create([
        'docket_number' => 'SALES-TODAY-001',
        'user_id' => auth()->id(),
        'counter_id' => $counter->id,
        'table_id' => $table->id,
        'payment_method' => 'Cash',
        'status' => 'paid',
        'subtotal' => 12500,
        'total' => 12500,
    ]);
    $todayOrder->items()->create([
        'product_type' => 'inventory',
        'product_id' => $product->id,
        'product_name' => 'Database Beverage',
        'quantity' => 2,
        'unit_price' => 6250,
        'line_total' => 12500,
    ]);

    $yesterdayOrder = PosOrder::query()->create([
        'docket_number' => 'SALES-YESTERDAY-001',
        'user_id' => auth()->id(),
        'counter_id' => $counter->id,
        'status' => 'paid',
        'subtotal' => 9000,
        'total' => 9000,
    ]);
    PosOrder::query()->whereKey($yesterdayOrder->id)->update([
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);
    $yesterdayOrder->items()->create([
        'product_type' => 'food',
        'product_id' => 1,
        'product_name' => 'Yesterday Dish',
        'quantity' => 1,
        'unit_price' => 9000,
        'line_total' => 9000,
    ]);

    $this->get('/sales')
        ->assertOk()
        ->assertSee('SALES-TODAY-001')
        ->assertSee('SALES-YESTERDAY-001')
        ->assertDontSee('SALE-001')
        ->assertViewHas('salesToday', 12500.0)
        ->assertViewHas('salesTodayCount', 1)
        ->assertViewHas('salesTodayProfit', 10500.0)
        ->assertViewHas('sales', fn ($sales) => $sales->first()['items'][0]['name'] === 'Database Beverage'
            && $sales->first()['cost'] === 2000.0)
        ->assertSee("dateFilter: 'today'", false)
        ->assertSee('Profit (Filtered)');
});

it('shows admin sale management actions and selected-sale delete control', function () {
    $counter = InventoryCounter::query()->firstOrFail();
    $order = PosOrder::query()->create([
        'docket_number' => 'SALES-ADMIN-001',
        'user_id' => auth()->id(),
        'counter_id' => $counter->id,
        'status' => 'paid',
        'subtotal' => 1000,
        'total' => 1000,
    ]);
    $order->items()->create([
        'product_type' => 'inventory',
        'product_id' => 1,
        'product_name' => 'Admin Test Item',
        'quantity' => 1,
        'unit_price' => 1000,
        'line_total' => 1000,
    ]);

    $this->get('/sales')
        ->assertOk()
        ->assertSee('Edit')
        ->assertSee('Backdate')
        ->assertSee('Delete')
        ->assertSee('selectedSales.length');
});

it('does not show admin sale management actions to non-admin staff', function () {
    auth()->user()->update([
        'role' => 'cashier',
        'permissions' => ['sales' => ['enabled' => true, 'components' => ['view' => true]]],
    ]);

    $this->get('/sales')
        ->assertOk()
        ->assertDontSee('@click.stop="openBackdate(sale)"', false)
        ->assertDontSee('@click.stop="openEdit(sale)"', false);
});

it('allows attendants to manage sales only when edit delete and backdate permissions are enabled', function () {
    $staff = User::factory()->create([
        'name' => 'Sales Manager',
        'role' => 'cashier',
        'permissions' => [
            'sales' => [
                'enabled' => true,
                'components' => ['view' => true, 'edit' => true, 'delete' => true, 'backdate' => true],
            ],
        ],
    ]);
    $counter = InventoryCounter::query()->firstOrFail();
    $order = PosOrder::query()->create([
        'docket_number' => 'SALES-STAFF-MANAGE-001',
        'user_id' => $staff->id,
        'counter_id' => $counter->id,
        'status' => 'paid',
        'subtotal' => 1000,
        'total' => 1000,
    ]);
    $foodItem = KitchenFoodItem::query()->create([
        'name' => 'Staff Editable Dish',
        'category' => 'Mains',
        'price' => 1200,
        'available' => true,
    ]);
    $order->items()->create([
        'product_type' => 'food',
        'product_id' => $foodItem->id,
        'product_name' => $foodItem->name,
        'quantity' => 1,
        'unit_price' => 1000,
        'line_total' => 1000,
    ]);

    $this->actingAs($staff)->get('/sales')
        ->assertOk()
        ->assertSee('@click.stop="openEdit(sale)"', false)
        ->assertSee('@click.stop="openBackdate(sale)"', false)
        ->assertSee('@click.stop="openDelete(sale)"', false);

    $this->patchJson('/sales/'.$order->id.'/backdate', ['date' => '2026-10-01'])->assertOk();
    $this->putJson('/sales/'.$order->id, [
        'table_id' => null,
        'items' => [['type' => 'food', 'id' => $foodItem->id, 'quantity' => 2]],
    ])->assertOk();
    expect((float) $order->fresh()->total)->toBe(2400.0);

    $this->deleteJson('/sales/'.$order->id)->assertOk();
    expect(PosOrder::query()->find($order->id))->toBeNull();
});

it('hides sales summary and actions when the attendant lacks those component permissions', function () {
    auth()->user()->update([
        'name' => 'Sales Attendant',
        'role' => 'cashier',
        'permissions' => ['sales' => ['enabled' => true, 'components' => []]],
    ]);

    $this->get('/sales')
        ->assertOk()
        ->assertSee('Sales Attendant')
        ->assertDontSee('Sales Total (Filtered)')
        ->assertDontSee('Profit (Filtered)')
        ->assertDontSee('<button @click="exportSales()"', false)
        ->assertDontSee('@click.stop="viewDocket(sale)"', false)
        ->assertDontSee('Administrator Profile')
        ->assertDontSee('href="/settings"', false);
});

it('limits attendant sales and summary totals to their own sales unless view-others is enabled', function () {
    $counter = InventoryCounter::query()->firstOrFail();
    $attendant = User::factory()->create([
        'name' => 'Sales Attendant',
        'role' => 'cashier',
        'status' => 'active',
        'permissions' => [
            'sales' => [
                'enabled' => true,
                'components' => ['view' => true, 'viewOthers' => false],
            ],
        ],
    ]);
    $otherAttendant = User::factory()->create(['name' => 'Other Attendant', 'role' => 'cashier']);

    foreach ([
        [$attendant, 'OWN-ATTENDANT-SALE', 1200],
        [$otherAttendant, 'OTHER-ATTENDANT-SALE', 3400],
    ] as [$user, $docket, $total]) {
        PosOrder::query()->create([
            'docket_number' => $docket,
            'user_id' => $user->id,
            'counter_id' => $counter->id,
            'status' => 'paid',
            'subtotal' => $total,
            'total' => $total,
        ]);
    }

    $this->actingAs($attendant)->get('/sales')
        ->assertOk()
        ->assertSee('OWN-ATTENDANT-SALE')
        ->assertDontSee('OTHER-ATTENDANT-SALE')
        ->assertViewHas('salesToday', 1200.0)
        ->assertViewHas('salesTodayCount', 1)
        ->assertViewHas('salesAttendants', fn ($names) => $names->all() === ['Sales Attendant']);

    $attendant->update([
        'permissions' => [
            'sales' => [
                'enabled' => true,
                'components' => ['view' => true, 'viewOthers' => true],
            ],
        ],
    ]);
    $this->actingAs($attendant->fresh())->get('/sales')
        ->assertOk()
        ->assertSee('OWN-ATTENDANT-SALE')
        ->assertSee('OTHER-ATTENDANT-SALE')
        ->assertViewHas('salesToday', 4600.0)
        ->assertViewHas('salesTodayCount', 2);
});

it('returns inventory and recipe ingredient stock when a sale is deleted', function () {
    $counter = InventoryCounter::query()->firstOrFail();
    $category = InventoryCategory::query()->firstOrFail();
    $product = InventoryProduct::query()->create([
        'category_id' => $category->id,
        'name' => 'Returned Sale Drink',
        'unit' => 'Bottle',
        'buy_price' => 1000,
        'sell_price' => 2000,
    ]);
    $stock = InventoryStock::query()->create([
        'product_id' => $product->id,
        'counter_id' => $counter->id,
        'quantity' => 8,
    ]);
    $materialCategory = KitchenRawMaterialCategory::query()->firstOrFail();
    $material = KitchenRawMaterial::query()->create([
        'category_id' => $materialCategory->id,
        'name' => 'Returned Sale Flour',
        'sku' => 'TEST-RETURN-FLOUR',
        'unit' => 'kg',
        'quantity' => 4,
        'average_cost' => 1000,
    ]);
    $order = PosOrder::query()->create([
        'docket_number' => 'SALES-RETURN-001',
        'user_id' => auth()->id(),
        'counter_id' => $counter->id,
        'status' => 'paid',
        'subtotal' => 6000,
        'total' => 6000,
    ]);
    $order->items()->createMany([
        [
            'product_type' => 'inventory',
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price' => 2000,
            'line_total' => 4000,
        ],
        [
            'product_type' => 'food',
            'product_id' => 123,
            'product_name' => 'Prepared Dish',
            'quantity' => 1,
            'unit_price' => 2000,
            'line_total' => 2000,
        ],
    ]);
    $stock->decrement('quantity', 2);
    InventoryMovement::query()->create([
        'product_id' => $product->id,
        'counter_id' => $counter->id,
        'user_id' => auth()->id(),
        'type' => 'sale',
        'quantity_delta' => -2,
        'reason' => 'POS docket '.$order->docket_number,
        'metadata' => ['order_id' => $order->id],
    ]);
    $material->decrement('quantity', 0.75);
    KitchenRawMaterialMovement::query()->create([
        'raw_material_id' => $material->id,
        'user_id' => auth()->id(),
        'type' => 'sale',
        'quantity_delta' => -0.75,
        'unit_cost' => 1000,
        'reason' => 'POS docket '.$order->docket_number,
        'metadata' => ['order_id' => $order->id, 'recipe_id' => 1],
    ]);

    $this->deleteJson('/sales/'.$order->id)->assertOk();

    expect((float) $stock->fresh()->quantity)->toBe(8.0)
        ->and((float) $material->fresh()->quantity)->toBe(4.0)
        ->and(InventoryMovement::query()->where('type', 'sale_return')->where('metadata->order_id', $order->id)->count())->toBe(1)
        ->and(KitchenRawMaterialMovement::query()->where('type', 'sale_return')->where('metadata->order_id', $order->id)->count())->toBe(1)
        ->and(PosOrder::query()->find($order->id))->toBeNull();
});

it('edits sale items using current prices and replaces the original stock deductions transactionally', function () {
    $counter = InventoryCounter::query()->firstOrFail();
    $category = InventoryCategory::query()->firstOrFail();
    $product = InventoryProduct::query()->create([
        'category_id' => $category->id,
        'name' => 'Edited Sale Drink',
        'unit' => 'Bottle',
        'buy_price' => 700,
        'sell_price' => 1500,
    ]);
    $stock = InventoryStock::query()->create([
        'product_id' => $product->id,
        'counter_id' => $counter->id,
        'quantity' => 10,
    ]);
    $order = PosOrder::query()->create([
        'docket_number' => 'SALES-EDIT-001',
        'user_id' => auth()->id(),
        'counter_id' => $counter->id,
        'status' => 'paid',
        'subtotal' => 3000,
        'total' => 3000,
    ]);
    $order->items()->create([
        'product_type' => 'inventory',
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 2,
        'unit_price' => 1500,
        'line_total' => 3000,
    ]);
    $stock->decrement('quantity', 2);
    InventoryMovement::query()->create([
        'product_id' => $product->id,
        'counter_id' => $counter->id,
        'user_id' => auth()->id(),
        'type' => 'sale',
        'quantity_delta' => -2,
        'reason' => 'POS docket '.$order->docket_number,
        'metadata' => ['order_id' => $order->id],
    ]);

    $this->putJson('/sales/'.$order->id, [
        'table_id' => null,
        'notes' => 'Corrected quantity',
        'items' => [['type' => 'inventory', 'id' => $product->id, 'quantity' => 3]],
    ])->assertOk()
        ->assertJsonPath('sale.total', 4500)
        ->assertJsonPath('sale.items.0.qty', 3);

    expect((float) $stock->fresh()->quantity)->toBe(7.0)
        ->and((float) $order->fresh()->total)->toBe(4500.0)
        ->and(InventoryMovement::query()->where('type', 'sale')->where('metadata->order_id', $order->id)->count())->toBe(2)
        ->and(InventoryMovement::query()->where('type', 'sale_return')->where('metadata->order_id', $order->id)->count())->toBe(1);

    $this->deleteJson('/sales/'.$order->id)->assertOk();

    expect((float) $stock->fresh()->quantity)->toBe(10.0)
        ->and(InventoryMovement::query()->where('type', 'sale_return')->where('metadata->order_id', $order->id)->count())->toBe(2);
});

it('rolls back the original stock return when an edited sale cannot be fulfilled', function () {
    $counter = InventoryCounter::query()->firstOrFail();
    $category = InventoryCategory::query()->firstOrFail();
    $product = InventoryProduct::query()->create([
        'category_id' => $category->id,
        'name' => 'Insufficient Edit Drink',
        'unit' => 'Bottle',
        'buy_price' => 700,
        'sell_price' => 1500,
    ]);
    $stock = InventoryStock::query()->create([
        'product_id' => $product->id,
        'counter_id' => $counter->id,
        'quantity' => 3,
    ]);
    $order = PosOrder::query()->create([
        'docket_number' => 'SALES-EDIT-ROLLBACK-001',
        'user_id' => auth()->id(),
        'counter_id' => $counter->id,
        'status' => 'paid',
        'subtotal' => 3000,
        'total' => 3000,
    ]);
    $order->items()->create([
        'product_type' => 'inventory',
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 2,
        'unit_price' => 1500,
        'line_total' => 3000,
    ]);
    InventoryMovement::query()->create([
        'product_id' => $product->id,
        'counter_id' => $counter->id,
        'user_id' => auth()->id(),
        'type' => 'sale',
        'quantity_delta' => -2,
        'reason' => 'POS docket '.$order->docket_number,
        'metadata' => ['order_id' => $order->id],
    ]);

    $this->putJson('/sales/'.$order->id, [
        'items' => [['type' => 'inventory', 'id' => $product->id, 'quantity' => 10]],
    ])->assertUnprocessable();

    expect((float) $stock->fresh()->quantity)->toBe(3.0)
        ->and((float) $order->fresh()->total)->toBe(3000.0)
        ->and(InventoryMovement::query()->where('type', 'sale_return')->where('metadata->order_id', $order->id)->count())->toBe(0)
        ->and(InventoryMovement::query()->where('type', 'sale')->where('metadata->order_id', $order->id)->first()->metadata)->not->toHaveKey('reversed_at');
});

it('backdates a sale while preserving the original time of day', function () {
    $counter = InventoryCounter::query()->firstOrFail();
    $order = PosOrder::query()->create([
        'docket_number' => 'SALES-BACKDATE-001',
        'user_id' => auth()->id(),
        'counter_id' => $counter->id,
        'status' => 'paid',
        'subtotal' => 1000,
        'total' => 1000,
    ]);
    $time = $order->created_at->format('H:i:s');

    $this->patchJson('/sales/'.$order->id.'/backdate', ['date' => '2026-10-01'])->assertOk();

    expect($order->fresh()->created_at->toDateString())->toBe('2026-10-01')
        ->and($order->fresh()->created_at->format('H:i:s'))->toBe($time);
});

it('rejects sale management actions for non-admin staff', function () {
    auth()->user()->update(['role' => 'cashier']);
    $counter = InventoryCounter::query()->firstOrFail();
    $order = PosOrder::query()->create([
        'docket_number' => 'SALES-FORBIDDEN-001',
        'user_id' => auth()->id(),
        'counter_id' => $counter->id,
        'status' => 'paid',
        'subtotal' => 1000,
        'total' => 1000,
    ]);

    $this->putJson('/sales/'.$order->id, [])->assertForbidden();
    $this->patchJson('/sales/'.$order->id.'/backdate', ['date' => '2026-10-01'])->assertForbidden();
    $this->deleteJson('/sales/'.$order->id)->assertForbidden();
});
