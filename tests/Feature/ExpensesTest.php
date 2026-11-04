<?php

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryCategory;
use App\Models\InventoryCounter;
use App\Models\InventoryProduct;
use App\Models\PosOrder;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('saves expense details and returns database-backed sales and P&L inputs', function () {
    $counter = InventoryCounter::query()->firstOrFail();
    $category = InventoryCategory::query()->firstOrFail();
    $product = InventoryProduct::query()->create([
        'category_id' => $category->id,
        'name' => 'P&L Costed Drink',
        'unit' => 'Bottle',
        'buy_price' => 1000,
        'sell_price' => 2500,
    ]);
    $order = PosOrder::query()->create([
        'docket_number' => 'PNL-ORDER-001',
        'user_id' => auth()->id(),
        'counter_id' => $counter->id,
        'status' => 'paid',
        'subtotal' => 5000,
        'total' => 5000,
    ]);
    $order->items()->create([
        'product_type' => 'inventory',
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 2,
        'unit_price' => 2500,
        'line_total' => 5000,
    ]);

    $expenseResponse = $this->postJson('/expenses', [
        'description' => 'Daily electricity',
        'category' => 'utilities',
        'amount' => 800,
        'payment_method' => 'Cash',
        'expense_date' => today()->toDateString(),
        'counter_id' => $counter->id,
        'is_recurring' => true,
        'repeat_interval' => 'Monthly',
    ])->assertCreated()
        ->assertJsonPath('expense.category', 'utilities')
        ->assertJsonPath('expense.amount', 800)
        ->assertJsonPath('expense.counterId', $counter->id)
        ->assertJsonPath('expense.isRecurring', true)
        ->assertJsonPath('expense.repeatInterval', 'Monthly');

    $expenseId = $expenseResponse->json('expense.recordId');
    $savedExpenseNumber = Expense::query()->findOrFail($expenseId)->expense_number;
    expect($savedExpenseNumber)
        ->toBe('EXP-'.str_pad((string) $expenseId, 6, '0', STR_PAD_LEFT))
        ->and(strlen($savedExpenseNumber))->toBeLessThanOrEqual(32);

    $this->get('/expenses')
        ->assertOk()
        ->assertSee('Revenue')
        ->assertSee('Cost of Goods Sold (estimated)')
        ->assertViewHas('expenseEntries', fn ($entries) => $entries->contains(fn ($entry) => $entry['recordId'] === $expenseId))
        ->assertViewHas('expenseSales', fn ($sales) => $sales->contains(fn ($sale) => $sale['counterId'] === $counter->id
            && $sale['sales'] === 5000.0
            && $sale['cost'] === 2000.0));
});

it('moves deleted expenses to Settings trash and supports restore and permanent deletion', function () {
    $expense = Expense::query()->create([
        'expense_number' => 'EXP-TRASH-001',
        'user_id' => auth()->id(),
        'category' => 'rent',
        'description' => 'Archived rent payment',
        'amount' => 1500,
        'payment_method' => 'Cash',
        'expense_date' => today()->toDateString(),
        'status' => 'cleared',
        'is_recurring' => false,
    ]);

    $this->deleteJson('/expenses/'.$expense->id)->assertOk();
    expect(Expense::query()->find($expense->id))->toBeNull()
        ->and(Expense::withTrashed()->findOrFail($expense->id)->trashed())->toBeTrue();

    $this->get('/settings')
        ->assertOk()
        ->assertSee('server.expenses');

    $this->postJson('/expenses/'.$expense->id.'/restore')->assertOk();
    expect(Expense::query()->find($expense->id))->not->toBeNull();

    $this->deleteJson('/expenses/'.$expense->id)->assertOk();
    $this->deleteJson('/expenses/'.$expense->id.'/permanent')->assertOk();
    expect(Expense::withTrashed()->find($expense->id))->toBeNull();
});

it('saves expense categories in Settings and makes them available to expenses', function () {
    $response = $this->postJson('/settings/expense-categories', ['name' => 'Equipment Hire'])
        ->assertCreated()
        ->assertJsonPath('category.label', 'Equipment Hire');

    $category = ExpenseCategory::query()->findOrFail($response->json('category.recordId'));
    expect($category->slug)->toBe('equipment_hire');

    $this->get('/expenses')
        ->assertOk()
        ->assertViewHas('expenseCategories', fn ($categories) => $categories->contains(fn ($item) => $item['id'] === $category->slug))
        ->assertSee('Equipment Hire');

    $expenseResponse = $this->postJson('/expenses', [
        'description' => 'Projector rental',
        'category' => $category->slug,
        'amount' => 2500,
        'expense_date' => today()->toDateString(),
        'is_recurring' => false,
    ])->assertCreated()
        ->assertJsonPath('expense.category', $category->slug);
    $expenseId = $expenseResponse->json('expense.recordId');

    $this->deleteJson('/settings/expense-categories/'.$category->id)->assertOk();

    $this->get('/settings')
        ->assertOk()
        ->assertSee('Equipment Hire')
        ->assertSee('server.expense-categories');

    $this->postJson('/settings/expense-categories/'.$category->id.'/restore')->assertOk();
    expect(ExpenseCategory::query()->find($category->id))->not->toBeNull();

    $this->deleteJson('/settings/expense-categories/'.$category->id)->assertOk();
    $this->deleteJson('/settings/expense-categories/'.$category->id.'/permanent')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This category is used by saved expenses and cannot be permanently deleted.');
    $this->deleteJson('/expenses/'.$expenseId)->assertOk();
    $this->deleteJson('/expenses/'.$expenseId.'/permanent')->assertOk();
    $this->deleteJson('/settings/expense-categories/'.$category->id.'/permanent')->assertOk();
    expect(ExpenseCategory::withTrashed()->find($category->id))->toBeNull();
});

it('provides independent date and counter filters for the P&L breakdown', function () {
    $this->get('/expenses')
        ->assertOk()
        ->assertSee('Filter breakdown')
        ->assertSee('breakdownDateFilter')
        ->assertSee('draftBreakdownCounter')
        ->assertSee('Last Week')
        ->assertSee('Custom Range');
});

it('shows only expense screens and actions granted by staff permissions', function () {
    $staff = User::factory()->create([
        'role' => 'cashier',
        'permissions' => [
            'expenses' => [
                'enabled' => true,
                'components' => ['add' => true, 'delete' => false, 'view' => false],
            ],
        ],
    ]);
    Expense::query()->create([
        'expense_number' => 'EXP-PRIVATE-001',
        'user_id' => $staff->id,
        'category' => 'rent',
        'description' => 'Private expense data',
        'amount' => 750,
        'payment_method' => 'Cash',
        'expense_date' => today()->toDateString(),
        'status' => 'cleared',
        'is_recurring' => false,
    ]);

    $this->actingAs($staff)->get('/expenses')
        ->assertOk()
        ->assertSee('Add Expense')
        ->assertSee('You can add expenses, but viewing expense records and profit breakdowns is not enabled for your account.')
        ->assertDontSee('Filter breakdown')
        ->assertDontSee('Move to Trash')
        ->assertDontSee('Private expense data');

    $staff->update([
        'permissions' => [
            'expenses' => [
                'enabled' => true,
                'components' => ['add' => false, 'delete' => false, 'view' => true],
            ],
        ],
    ]);
    $this->actingAs($staff)->get('/expenses')
        ->assertOk()
        ->assertSee('Filter breakdown')
        ->assertSee('Revenue')
        ->assertDontSee('Add Expense')
        ->assertDontSee('Move to Trash');

    $staff->update([
        'permissions' => [
            'expenses' => [
                'enabled' => true,
                'components' => ['add' => true, 'delete' => true, 'view' => true],
            ],
        ],
    ]);
    $this->actingAs($staff)->get('/expenses')
        ->assertOk()
        ->assertSee('Add Expense')
        ->assertSee('Move to Trash');
});
