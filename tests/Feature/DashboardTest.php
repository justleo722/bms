<?php

use App\Models\Expense;
use App\Models\InventoryCategory;
use App\Models\InventoryCounter;
use App\Models\InventoryProduct;
use App\Models\InventoryStock;
use App\Models\PosOrder;
use App\Models\User;

function createDashboardOrder(User $user, InventoryCounter $counter, InventoryProduct $product, string $docket, int $quantity, float $price): PosOrder
{
    $order = PosOrder::query()->create([
        'docket_number' => $docket,
        'user_id' => $user->id,
        'counter_id' => $counter->id,
        'status' => 'paid',
        'subtotal' => $quantity * $price,
        'total' => $quantity * $price,
    ]);
    $order->items()->create([
        'product_type' => 'inventory',
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => $quantity,
        'unit_price' => $price,
        'line_total' => $quantity * $price,
    ]);

    return $order;
}

it('renders owner dashboard metrics and widgets from current database records', function () {
    $administrator = User::factory()->create(['role' => 'administrator']);
    $counter = InventoryCounter::query()->firstOrFail();
    $category = InventoryCategory::query()->firstOrFail();
    $product = InventoryProduct::query()->create([
        'category_id' => $category->id,
        'name' => 'Dashboard Test Lager',
        'unit' => 'Bottle',
        'buy_price' => 1000,
        'sell_price' => 2500,
    ]);
    InventoryStock::query()->create([
        'product_id' => $product->id,
        'counter_id' => $counter->id,
        'quantity' => 2,
        'reorder_level' => 5,
    ]);
    createDashboardOrder($administrator, $counter, $product, 'DASH-OWNER-001', 2, 2500);
    Expense::query()->create([
        'expense_number' => 'DASH-EXP-001',
        'user_id' => $administrator->id,
        'counter_id' => $counter->id,
        'category' => 'rent',
        'description' => 'Dashboard test rent',
        'amount' => 800,
        'payment_method' => 'Cash',
        'expense_date' => today()->toDateString(),
        'status' => 'cleared',
        'is_recurring' => false,
    ]);

    $this->actingAs($administrator)->get('/')
        ->assertOk()
        ->assertViewHas('dashboardIsOwner', true)
        ->assertViewHas('dashboardTodaySales', 5000.0)
        ->assertViewHas('dashboardTodayProfit', 2200.0)
        ->assertViewHas('dashboardTodayExpenses', 800.0)
        ->assertViewHas('dashboardTopSellingItems', fn ($items) => $items->contains(fn ($item) => $item['name'] === 'Dashboard Test Lager' && $item['quantity'] === 2))
        ->assertViewHas('dashboardStockAlerts', fn ($alerts) => $alerts->contains(fn ($alert) => $alert['item'] === 'Dashboard Test Lager'))
        ->assertViewHas('dashboardRecentSales', fn ($sales) => $sales->contains(fn ($sale) => $sale['id'] === 'DASH-OWNER-001'))
        ->assertSee('Dashboard test rent')
        ->assertSee('Dashboard Test Lager')
        ->assertSee('Stock Alerts');
});

it('limits attendant dashboard totals and activity to the assigned counter and enabled permissions', function () {
    $counter = InventoryCounter::query()->firstOrFail();
    $otherCounter = InventoryCounter::query()->create(['name' => 'Dashboard Other Counter', 'slug' => 'dashboard-other-counter']);
    $category = InventoryCategory::query()->firstOrFail();
    $product = InventoryProduct::query()->create([
        'category_id' => $category->id,
        'name' => 'Assigned Counter Lager',
        'unit' => 'Bottle',
        'buy_price' => 1000,
        'sell_price' => 2500,
    ]);
    $otherProduct = InventoryProduct::query()->create([
        'category_id' => $category->id,
        'name' => 'Private Counter Lager',
        'unit' => 'Bottle',
        'buy_price' => 1000,
        'sell_price' => 2500,
    ]);
    InventoryStock::query()->create([
        'product_id' => $product->id,
        'counter_id' => $counter->id,
        'quantity' => 1,
        'reorder_level' => 4,
    ]);
    InventoryStock::query()->create([
        'product_id' => $otherProduct->id,
        'counter_id' => $otherCounter->id,
        'quantity' => 0,
        'reorder_level' => 5,
    ]);
    $staff = User::factory()->create([
        'role' => 'manager',
        'counter_id' => $counter->id,
        'permissions' => [
            'dashboard' => ['enabled' => true, 'components' => [
                'todaySales' => true, 'todayProfit' => false, 'todayExpenses' => false, 'quickActions' => false,
            ]],
            'inventory' => ['enabled' => true, 'components' => ['value' => false]],
        ],
    ]);
    createDashboardOrder($staff, $counter, $product, 'DASH-BRANCH-001', 1, 2500);
    createDashboardOrder($staff, $otherCounter, $otherProduct, 'DASH-PRIVATE-001', 3, 2500);

    $this->actingAs($staff)->get('/')
        ->assertOk()
        ->assertViewHas('dashboardIsOwner', false)
        ->assertViewHas('dashboardCounterLabel', $counter->name)
        ->assertViewHas('dashboardTodaySales', 2500.0)
        ->assertViewHas('dashboardTodayProfit', null)
        ->assertViewHas('dashboardTodayExpenses', null)
        ->assertViewHas('dashboardTopSellingItems', fn ($items) => $items->count() === 1 && $items->first()['name'] === 'Assigned Counter Lager')
        ->assertViewHas('dashboardStockAlerts', fn ($alerts) => $alerts->count() === 1 && $alerts->first()['counter'] === $counter->name)
        ->assertSee('DASH-BRANCH-001')
        ->assertDontSee('DASH-PRIVATE-001')
        ->assertDontSee('Private Counter Lager')
        ->assertDontSee('Recent Expenses Today');
});

it('omits sensitive dashboard activity when its individual permissions are disabled', function () {
    $counter = InventoryCounter::query()->firstOrFail();
    $category = InventoryCategory::query()->firstOrFail();
    $product = InventoryProduct::query()->create([
        'category_id' => $category->id,
        'name' => 'Hidden Dashboard Lager',
        'unit' => 'Bottle',
        'buy_price' => 1000,
        'sell_price' => 2500,
    ]);
    $staff = User::factory()->create([
        'role' => 'cashier',
        'permissions' => [
            'dashboard' => ['enabled' => true, 'components' => [
                'todaySales' => false, 'todayProfit' => true, 'todayExpenses' => false, 'quickActions' => false,
            ]],
        ],
    ]);
    createDashboardOrder($staff, $counter, $product, 'DASH-HIDDEN-001', 1, 2500);

    $this->actingAs($staff)->get('/')
        ->assertOk()
        ->assertViewHas('dashboardTodaySales', null)
        ->assertViewHas('dashboardTopSellingItems', fn ($items) => $items->isEmpty())
        ->assertViewHas('dashboardRecentSales', fn ($sales) => $sales->isEmpty())
        ->assertDontSee('DASH-HIDDEN-001')
        ->assertDontSee('Hidden Dashboard Lager')
        ->assertDontSee('Recent Sales Today');
});
