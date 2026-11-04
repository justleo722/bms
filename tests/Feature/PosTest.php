<?php

use App\Models\InventoryCategory;
use App\Models\InventoryCounter;
use App\Models\InventoryProduct;
use App\Models\InventoryStock;
use App\Models\KitchenFoodItem;
use App\Models\KitchenRawMaterial;
use App\Models\KitchenRawMaterialCategory;
use App\Models\KitchenRecipe;
use App\Models\PosOrder;
use App\Models\PosPaymentMode;
use App\Models\PosTable;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('uses database products, categories, counters, tables, and payment methods in the POS', function () {
    $category = InventoryCategory::query()->where('slug', 'spirits')->firstOrFail();
    $counter = InventoryCounter::query()->firstOrFail();
    $product = InventoryProduct::query()->create([
        'category_id' => $category->id,
        'name' => 'POS Database Gin',
        'sku' => 'POS-DB-GIN',
        'unit' => 'Bottle',
        'buy_price' => 1000,
        'sell_price' => 2000,
        'image' => 'data:image/png;base64,ZmFrZQ==',
    ]);
    InventoryStock::query()->create([
        'product_id' => $product->id,
        'counter_id' => $counter->id,
        'quantity' => 8,
        'reorder_level' => 2,
    ]);
    KitchenFoodItem::query()->create([
        'name' => 'POS Database Dish',
        'category' => 'Test Mains',
        'price' => 7500,
        'image' => 'data:image/jpeg;base64,ZmFrZQ==',
        'available' => true,
    ]);

    $tableResponse = $this->postJson('/settings/tables', ['name' => 'POS Garden Table'])->assertCreated();
    $tableId = $tableResponse->json('table.id');
    $modeResponse = $this->putJson('/settings/payment-modes', [
        'modes' => [[
            'id' => null,
            'name' => 'POS Database Transfer',
            'enabled' => true,
            'requiresRef' => true,
            'refLabel' => 'Transfer reference',
            'icon' => '🏦',
        ]],
    ])->assertOk();

    $this->get('/pos')
        ->assertOk()
        ->assertSee(':src="item.image"', false)
        ->assertDontSee('x-text="item.image"', false)
        ->assertViewHas('posCounters', fn ($counters) => $counters->contains(fn ($item) => $item['id'] === $counter->id))
        ->assertViewHas('posProducts', fn ($products) => $products->contains(fn ($item) => $item['name'] === 'POS Database Gin' && $item['categoryLabel'] === $category->name)
            && $products->contains(fn ($item) => $item['name'] === 'POS Database Gin' && $item['image'] === 'data:image/png;base64,ZmFrZQ==')
            && $products->contains(fn ($item) => $item['name'] === 'POS Database Dish' && $item['image'] === 'data:image/jpeg;base64,ZmFrZQ=='))
        ->assertViewHas('posCategories', fn ($categories) => $categories->contains(fn ($item) => $item['label'] === $category->name)
            && $categories->contains(fn ($item) => $item['label'] === 'Test Mains'))
        ->assertViewHas('posTables', fn ($tables) => $tables->contains('POS Garden Table'))
        ->assertViewHas('posPaymentModes', fn ($modes) => collect($modes)->contains(fn ($item) => $item['id'] === $modeResponse->json('modes.0.id')));

    expect(PosTable::query()->findOrFail($tableId)->name)->toBe('POS Garden Table');
    expect(PosPaymentMode::query()->where('name', 'POS Database Transfer')->value('reference_label'))->toBe('Transfer reference');
});

it('saves a docket and deducts inventory and recipe ingredient stock transactionally', function () {
    $counter = InventoryCounter::query()->firstOrFail();
    $inventoryCategory = InventoryCategory::query()->firstOrFail();
    $inventoryProduct = InventoryProduct::query()->create([
        'category_id' => $inventoryCategory->id,
        'name' => 'Docket Test Beverage',
        'sku' => 'DOCKET-TEST-BEVERAGE',
        'unit' => 'Bottle',
        'buy_price' => 1000,
        'sell_price' => 2500,
    ]);
    $stock = InventoryStock::query()->create([
        'product_id' => $inventoryProduct->id,
        'counter_id' => $counter->id,
        'quantity' => 5,
        'reorder_level' => 1,
    ]);
    $materialCategory = KitchenRawMaterialCategory::query()->firstOrFail();
    $material = KitchenRawMaterial::query()->create([
        'category_id' => $materialCategory->id,
        'name' => 'Docket Test Flour',
        'sku' => 'DOCKET-TEST-FLOUR',
        'unit' => 'kg',
        'quantity' => 4,
        'average_cost' => 1000,
    ]);
    $recipe = KitchenRecipe::query()->create([
        'name' => 'Docket Test Recipe',
        'code' => 'DOCKET-TEST-RECIPE',
        'category' => 'Mains',
        'output_quantity' => 2,
        'output_unit' => 'servings',
        'active' => true,
    ]);
    $recipe->ingredients()->create([
        'raw_material_id' => $material->id,
        'quantity' => 1,
        'unit' => 'kg',
        'waste_percent' => 10,
    ]);
    $foodItem = KitchenFoodItem::query()->create([
        'name' => 'Docket Test Meal',
        'category' => 'Mains',
        'price' => 8000,
        'available' => true,
    ]);
    $foodItem->recipes()->attach($recipe->id);
    $table = PosTable::query()->firstOrFail();
    $paymentMode = PosPaymentMode::query()->where('enabled', true)->where('requires_reference', false)->firstOrFail();

    $response = $this->postJson('/pos/checkout', [
        'counter_id' => $counter->id,
        'table' => $table->name,
        'notes' => 'No onions',
        'payment_mode_id' => $paymentMode->id,
        'items' => [
            ['type' => 'inventory', 'id' => $inventoryProduct->id, 'quantity' => 2],
            ['type' => 'food', 'id' => $foodItem->id, 'quantity' => 2],
        ],
    ])->assertCreated()
        ->assertJsonPath('sale.table', $table->name)
        ->assertJsonPath('sale.notes', 'No onions')
        ->assertJsonPath('sale.paymentMethod', $paymentMode->name)
        ->assertJsonPath('sale.total', 21000);

    $orderId = $response->json('sale.orderId');
    expect((float) $stock->fresh()->quantity)->toBe(3.0)
        ->and((float) $material->fresh()->quantity)->toBe(2.9)
        ->and(PosOrder::query()->findOrFail($orderId)->items()->count())->toBe(2);

    $this->get('/pos/dockets/'.$orderId)
        ->assertOk()
        ->assertJsonPath('sale.id', $response->json('sale.id'))
        ->assertJsonPath('sale.items.0.name', 'Docket Test Beverage');
    $this->deleteJson('/settings/tables/'.$table->id)->assertUnprocessable();
});

it('rejects a checkout with insufficient stock without creating a docket', function () {
    $counter = InventoryCounter::query()->firstOrFail();
    $category = InventoryCategory::query()->firstOrFail();
    $product = InventoryProduct::query()->create([
        'category_id' => $category->id,
        'name' => 'Insufficient POS Stock',
        'sku' => 'POS-INSUFFICIENT',
        'unit' => 'Bottle',
        'buy_price' => 100,
        'sell_price' => 200,
    ]);
    InventoryStock::query()->create([
        'product_id' => $product->id,
        'counter_id' => $counter->id,
        'quantity' => 1,
    ]);

    $this->postJson('/pos/checkout', [
        'counter_id' => $counter->id,
        'items' => [['type' => 'inventory', 'id' => $product->id, 'quantity' => 2]],
    ])->assertUnprocessable();

    expect(PosOrder::query()->count())->toBe(0);
});

it('limits an assigned attendant to their counter and rejects checkout at another counter', function () {
    $assignedCounter = InventoryCounter::query()->create([
        'name' => 'Assigned POS Counter',
        'slug' => 'assigned-pos-counter',
    ]);
    $otherCounter = InventoryCounter::query()->firstOrFail();
    $category = InventoryCategory::query()->firstOrFail();
    $product = InventoryProduct::query()->create([
        'category_id' => $category->id,
        'name' => 'Assigned Counter Product',
        'sku' => 'POS-ASSIGNED-COUNTER',
        'unit' => 'Bottle',
        'buy_price' => 100,
        'sell_price' => 200,
    ]);
    InventoryStock::query()->create([
        'product_id' => $product->id,
        'counter_id' => $assignedCounter->id,
        'quantity' => 5,
    ]);
    $otherProduct = InventoryProduct::query()->create([
        'category_id' => $category->id,
        'name' => 'Other Counter Product',
        'sku' => 'POS-OTHER-COUNTER',
        'unit' => 'Bottle',
        'buy_price' => 100,
        'sell_price' => 200,
    ]);
    InventoryStock::query()->create([
        'product_id' => $otherProduct->id,
        'counter_id' => $otherCounter->id,
        'quantity' => 5,
    ]);

    $attendant = User::factory()->create([
        'role' => 'cashier',
        'counter_id' => $assignedCounter->id,
        'permissions' => ['pos' => ['enabled' => true, 'components' => ['sell' => true, 'printDocket' => true]]],
    ]);
    $this->actingAs($attendant)
        ->get('/pos')
        ->assertOk()
        ->assertViewHas('posCounters', fn ($counters) => $counters->count() === 1 && $counters->first()['id'] === $assignedCounter->id)
        ->assertViewHas('posProducts', fn ($products) => $products->contains(fn ($item) => $item['name'] === 'Assigned Counter Product')
            && ! $products->contains(fn ($item) => $item['name'] === 'Other Counter Product'));

    $this->postJson('/pos/checkout', [
        'counter_id' => $otherCounter->id,
        'items' => [['type' => 'inventory', 'id' => $product->id, 'quantity' => 1]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('counter_id');

    $otherOrder = PosOrder::query()->create([
        'docket_number' => 'OTHER-COUNTER-DOCKET',
        'counter_id' => $otherCounter->id,
        'status' => 'paid',
        'subtotal' => 200,
        'total' => 200,
    ]);
    $this->getJson('/pos/dockets/'.$otherOrder->id)->assertForbidden();

    expect(PosOrder::query()->count())->toBe(1)
        ->and((float) InventoryStock::query()->where('product_id', $product->id)->where('counter_id', $assignedCounter->id)->value('quantity'))->toBe(5.0);
});
