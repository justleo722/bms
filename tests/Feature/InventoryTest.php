<?php

use App\Models\InventoryCategory;
use App\Models\InventoryCounter;
use App\Models\InventoryMovement;
use App\Models\InventoryProduct;
use App\Models\InventoryStock;
use App\Models\InventoryUnit;
use App\Models\User;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('requires a signed-in user before inventory data can be changed', function () {
    auth()->logout();

    $this->postJson('/inventory/products', [])->assertUnauthorized();
    $this->postJson('/settings/inventory/categories', ['name' => 'Blocked'])->assertUnauthorized();
});

it('uses a neutral placeholder on the add product form', function () {
    $this->get('/inventory')->assertOk()
        ->assertSee('placeholder="e.g. Cocacola"', false)
        ->assertDontSee('e.g. Konyagi 350ml', false);
});

it('shows inventory controls only for their matching staff permissions', function () {
    $staff = User::factory()->create([
        'role' => 'cashier',
        'permissions' => ['inventory' => ['enabled' => true, 'components' => ['value' => true]]],
    ]);

    $this->actingAs($staff)->get('/inventory')
        ->assertOk()
        ->assertDontSee('@click="openAddProductModal()"', false)
        ->assertDontSee('@click="openImport()"', false)
        ->assertDontSee('@click="openBulkAdjust()"', false)
        ->assertDontSee('@click="openRestock()"', false)
        ->assertDontSee('@click="openTransfer()"', false)
        ->assertDontSee('From Counter')
        ->assertDontSee('Select Products to Transfer')
        ->assertDontSee('@click="openEdit(product)"', false)
        ->assertDontSee('@click="openDelete(product)"', false);

    foreach ([
        'addRestock' => ['@click="openAddProductModal()"', '@click="openImport()"', '@click="openRestock()"'],
        'editAdjust' => ['@click="openBulkAdjust()"', '@click="openEdit(product)"'],
        'transfer' => ['@click="openTransfer()"', 'From Counter', 'Select Products to Transfer'],
        'delete' => ['@click="openDelete(product)"'],
        'value' => ['Inventory Value', 'Profit Estimate'],
    ] as $permission => $markers) {
        $staff->update([
            'permissions' => ['inventory' => ['enabled' => true, 'components' => [$permission => true]]],
        ]);
        $response = $this->get('/inventory')->assertOk();
        foreach ($markers as $marker) {
            $response->assertSee($marker, false);
        }
    }
});

it('renders Inventory for an attendant after PIN login with limited inventory permissions', function () {
    $staff = User::factory()->create([
        'name' => 'Inventory Attendant',
        'role' => 'cashier',
        'pin' => '4816',
        'status' => 'active',
        'permissions' => [
            'inventory' => [
                'enabled' => true,
                'components' => ['addRestock' => false, 'editAdjust' => false, 'transfer' => false, 'delete' => false, 'value' => true],
            ],
        ],
    ]);

    $this->post('/logout')->assertRedirect(route('login'));
    $this->post('/login', ['pin' => $staff->pin])->assertRedirect('/inventory');
    $this->get('/inventory')
        ->assertOk()
        ->assertSee('Inventory Attendant')
        ->assertSee('Inventory')
        ->assertSee('Inventory Value')
        ->assertSee('Profit Estimate')
        ->assertDontSee('@click="openAddProductModal()"', false)
        ->assertDontSee('@click="openEdit(product)"', false);
});

it('denies inventory access when all inventory permissions are disabled', function () {
    $staff = User::factory()->create([
        'role' => 'cashier',
        'permissions' => [
            'inventory' => [
                'enabled' => true,
                'components' => ['addRestock' => false, 'editAdjust' => false, 'transfer' => false, 'delete' => false, 'value' => false],
            ],
        ],
    ]);

    $this->actingAs($staff)->get('/inventory')->assertRedirect('/no-access');
});

it('limits assigned attendants to their inventory counter and its stock actions', function () {
    $assignedCounter = InventoryCounter::query()->create([
        'name' => 'Assigned Inventory Counter',
        'slug' => 'assigned-inventory-counter',
    ]);
    $otherCounter = InventoryCounter::query()->create([
        'name' => 'Restricted Inventory Counter',
        'slug' => 'restricted-inventory-counter',
    ]);
    $category = InventoryCategory::query()->where('slug', 'spirits')->firstOrFail();
    $assignedProduct = InventoryProduct::query()->create([
        'category_id' => $category->id,
        'name' => 'Assigned Counter Product',
        'unit' => 'Bottle',
        'buy_price' => 100,
        'sell_price' => 200,
    ]);
    $assignedStock = $assignedProduct->stocks()->create([
        'counter_id' => $assignedCounter->id,
        'quantity' => 10,
    ]);
    $restrictedProduct = InventoryProduct::query()->create([
        'category_id' => $category->id,
        'name' => 'Restricted Counter Product',
        'unit' => 'Bottle',
        'buy_price' => 100,
        'sell_price' => 200,
    ]);
    $restrictedStock = $restrictedProduct->stocks()->create([
        'counter_id' => $otherCounter->id,
        'quantity' => 10,
    ]);
    $staff = User::factory()->create([
        'role' => 'cashier',
        'counter_id' => $assignedCounter->id,
        'permissions' => [
            'inventory' => [
                'enabled' => true,
                'components' => array_fill_keys(['addRestock', 'editAdjust', 'transfer', 'delete', 'value'], true),
            ],
        ],
    ]);

    $this->actingAs($staff)->get('/inventory')
        ->assertOk()
        ->assertSee('Assigned Inventory Counter')
        ->assertSee('Assigned Counter Product')
        ->assertDontSee('Restricted Inventory Counter')
        ->assertDontSee('Restricted Counter Product');

    $this->postJson('/inventory/stocks/'.$restrictedStock->id.'/adjust', [
        'mode' => 'add',
        'quantity' => 1,
        'reason' => 'Unauthorized counter adjustment',
    ])->assertForbidden();

    $this->postJson('/inventory/restock', [
        'items' => [['stock_id' => $restrictedStock->id, 'quantity' => 1]],
    ])->assertForbidden();

    $this->postJson('/inventory/transfer', [
        'to_counter_id' => $otherCounter->id,
        'items' => [['stock_id' => $assignedStock->id, 'quantity' => 1]],
    ])->assertForbidden();
});

it('enforces each inventory action permission on its backend endpoint', function () {
    $category = InventoryCategory::query()->where('slug', 'spirits')->firstOrFail();
    $counter = InventoryCounter::query()->where('name', 'Main Counter')->firstOrFail();
    $product = InventoryProduct::query()->create([
        'category_id' => $category->id,
        'name' => 'Permission Checked Product',
        'unit' => 'Bottle',
        'buy_price' => 100,
        'sell_price' => 200,
    ]);
    $stock = $product->stocks()->create([
        'counter_id' => $counter->id,
        'quantity' => 5,
    ]);
    $staff = User::factory()->create([
        'role' => 'cashier',
        'permissions' => ['inventory' => ['enabled' => true, 'components' => []]],
    ]);
    $this->actingAs($staff);

    $this->postJson('/inventory/products', [])->assertForbidden();
    $this->postJson('/inventory/restock', [])->assertForbidden();
    $this->postJson('/inventory/products/import', [])->assertForbidden();
    $this->putJson('/inventory/products/'.$product->id, [])->assertForbidden();
    $this->postJson('/inventory/stocks/'.$stock->id.'/adjust', [])->assertForbidden();
    $this->postJson('/inventory/adjustments', [])->assertForbidden();
    $this->postJson('/inventory/transfer', [])->assertForbidden();
    $this->deleteJson('/inventory/products/'.$product->id)->assertForbidden();
});

it('persists inventory categories, counters, and units through Settings', function () {
    $this->postJson('/settings/inventory/categories', ['name' => 'Cocktail Mixers'])
        ->assertCreated()
        ->assertJsonPath('category.name', 'Cocktail Mixers');
    $category = InventoryCategory::query()->where('name', 'Cocktail Mixers')->firstOrFail();
    $this->deleteJson('/settings/inventory/categories/'.$category->id)->assertOk();
    expect(InventoryCategory::query()->whereKey($category->id)->exists())->toBeFalse();

    $this->postJson('/settings/inventory/counters', ['name' => 'Garden Bar'])
        ->assertCreated()
        ->assertJsonPath('counter.name', 'Garden Bar');
    $counter = InventoryCounter::query()->where('name', 'Garden Bar')->firstOrFail();
    $this->deleteJson('/settings/inventory/counters/'.$counter->id)->assertOk();

    $this->postJson('/settings/inventory/units', ['name' => 'Growler'])
        ->assertCreated()
        ->assertJsonPath('unit', 'Growler');
    $this->deleteJson('/settings/inventory/units', ['name' => 'Growler'])->assertOk();
    expect(InventoryUnit::query()->where('name', 'Growler')->exists())->toBeFalse();
});

it('creates products and requires stock adjustments to change existing quantities', function () {
    $category = InventoryCategory::query()->where('slug', 'spirits')->firstOrFail();
    $counter = InventoryCounter::query()->where('name', 'Main Counter')->firstOrFail();

    $response = $this->postJson('/inventory/products', [
        'name' => 'Test Gin',
        'category_id' => $category->id,
        'unit' => 'Bottle',
        'counter_id' => $counter->id,
        'quantity' => 12,
        'reorder_level' => 4,
        'buy_price' => 1000,
        'sell_price' => 1800,
        'supplier' => 'Test Supplier',
    ])->assertCreated()
        ->assertJsonPath('product.name', 'Test Gin')
        ->assertJsonPath('product.stock', 12);

    $productId = $response->json('product.productId');
    $stockId = $response->json('product.id');

    $this->putJson('/inventory/products/'.$productId, [
        'name' => 'Premium Test Gin',
        'category_id' => $category->id,
        'unit' => 'Bottle',
        'buy_price' => 1100,
        'sell_price' => 1900,
        'supplier' => 'Updated Supplier',
        'stock_id' => $stockId,
        'quantity' => 10,
        'reorder_level' => 5,
    ])->assertOk()->assertJsonPath('product.name', 'Premium Test Gin');

    expect((float) InventoryStock::query()->findOrFail($stockId)->quantity)->toBe(12.0)
        ->and(InventoryMovement::query()->where('product_id', $productId)->count())->toBe(1);

    $this->putJson('/inventory/stocks/'.$stockId, [
        'quantity' => 4,
        'reorder_level' => 6,
    ])->assertOk()->assertJsonPath('product.stock', 12);

    expect((float) InventoryStock::query()->findOrFail($stockId)->quantity)->toBe(12.0)
        ->and(InventoryMovement::query()->where('product_id', $productId)->count())->toBe(1);

    $this->postJson('/inventory/stocks/'.$stockId.'/adjust', [
        'mode' => 'set',
        'quantity' => 10,
        'reason' => 'Stock count correction',
    ])->assertOk()->assertJsonPath('product.stock', 10);

    expect((float) InventoryStock::query()->findOrFail($stockId)->quantity)->toBe(10.0)
        ->and(InventoryMovement::query()->where('product_id', $productId)->count())->toBe(2);
});

it('saves inventory product images and returns them in the stock payload', function () {
    $category = InventoryCategory::query()->where('slug', 'spirits')->firstOrFail();
    $counter = InventoryCounter::query()->where('name', 'Main Counter')->firstOrFail();
    $image = 'data:image/png;base64,ZmFrZQ==';

    $response = $this->postJson('/inventory/products', [
        'name' => 'Product with image',
        'category_id' => $category->id,
        'unit' => 'Bottle',
        'counter_id' => $counter->id,
        'quantity' => 3,
        'reorder_level' => 1,
        'buy_price' => 100,
        'sell_price' => 200,
        'image' => $image,
    ])->assertCreated()
        ->assertJsonPath('product.image', $image);

    $product = InventoryProduct::query()->findOrFail($response->json('product.productId'));
    expect($product->image)->toBe($image);

    $this->putJson('/inventory/products/'.$product->id, [
        'name' => $product->name,
        'category_id' => $category->id,
        'unit' => 'Bottle',
        'buy_price' => 100,
        'sell_price' => 200,
        'stock_id' => $response->json('product.id'),
        'quantity' => 3,
        'reorder_level' => 1,
        'image' => null,
    ])->assertOk()->assertJsonPath('product.image', '');

    expect($product->fresh()->image)->toBeNull();
});

it('imports products without requiring a unit column and uses the configured default', function () {
    $category = InventoryCategory::query()->where('slug', 'spirits')->firstOrFail();
    $counter = InventoryCounter::query()->where('name', 'Main Counter')->firstOrFail();
    $file = UploadedFile::fake()->createWithContent(
        'inventory.csv',
        "Product Name,Stock Quantity,Buying Price (TSh),Selling Price (TSh)\nImported Gin,8,1000,1800\n"
    );

    $this->post('/inventory/products/import', [
        'file' => $file,
        'category_id' => $category->id,
        'counter_id' => $counter->id,
    ])->assertOk()->assertJsonPath('ok', true);

    $product = InventoryProduct::query()->where('name', 'Imported Gin')->firstOrFail();
    expect($product->unit)->toBe('Units')
        ->and((float) $product->stocks()->firstOrFail()->quantity)->toBe(8.0);
});

it('restocks, adjusts, and transfers quantities transactionally between counters', function () {
    $category = InventoryCategory::query()->where('slug', 'beer')->firstOrFail();
    $source = InventoryCounter::query()->where('name', 'Main Counter')->firstOrFail();
    $destination = InventoryCounter::query()->where('name', 'VIP Counter')->firstOrFail();
    $product = InventoryProduct::query()->create([
        'category_id' => $category->id,
        'name' => 'Test Lager',
        'unit' => 'Bottle',
        'buy_price' => 1000,
        'sell_price' => 2000,
    ]);
    $stock = $product->stocks()->create([
        'counter_id' => $source->id,
        'quantity' => 10,
        'reorder_level' => 3,
    ]);

    $this->postJson('/inventory/restock', [
        'items' => [['stock_id' => $stock->id, 'quantity' => 5, 'buy_price' => 1200]],
    ])->assertOk()
        ->assertJsonPath('products.0.buyPrice', 1200)
        ->assertJsonPath('products.0.sellPrice', 2000);

    $this->postJson('/inventory/stocks/'.$stock->id.'/adjust', [
        'mode' => 'remove',
        'quantity' => 2,
        'reason' => 'Damaged units',
    ])->assertOk();

    $this->postJson('/inventory/transfer', [
        'to_counter_id' => $destination->id,
        'items' => [['stock_id' => $stock->id, 'quantity' => 3]],
    ])->assertOk();

    expect((float) $stock->fresh()->quantity)->toBe(10.0)
        ->and((float) InventoryStock::query()->where('product_id', $product->id)->where('counter_id', $destination->id)->value('quantity'))->toBe(3.0)
        ->and(InventoryMovement::query()->where('product_id', $product->id)->count())->toBe(4);
});

it('moves deleted products to trash and allows restoration but preserves movement history', function () {
    $category = InventoryCategory::query()->where('slug', 'spirits')->firstOrFail();
    $counter = InventoryCounter::query()->where('name', 'Main Counter')->firstOrFail();
    $response = $this->postJson('/inventory/products', [
        'name' => 'Archived Product',
        'category_id' => $category->id,
        'unit' => 'Bottle',
        'counter_id' => $counter->id,
        'quantity' => 2,
        'reorder_level' => 1,
        'buy_price' => 1000,
        'sell_price' => 2000,
    ])->assertCreated();
    $productId = $response->json('product.productId');

    $this->deleteJson('/inventory/products/'.$productId)->assertOk();
    expect(InventoryProduct::query()->find($productId))->toBeNull()
        ->and(InventoryProduct::withTrashed()->findOrFail($productId)->trashed())->toBeTrue();

    $this->postJson('/inventory/products/'.$productId.'/restore')->assertOk();
    expect(InventoryProduct::query()->find($productId))->not->toBeNull();

    $this->deleteJson('/inventory/products/'.$productId.'/permanent')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This product has stock movement history and cannot be permanently deleted without losing the audit trail.');
});
