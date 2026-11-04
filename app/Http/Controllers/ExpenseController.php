<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryCounter;
use App\Models\PosOrder;
use App\Models\PosPaymentMode;
use App\Support\PosOrderCostCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    private const REPEAT_INTERVALS = ['Everyday', 'Weekly', 'Monthly', 'Quarterly', 'Yearly'];

    public function index()
    {
        $orders = PosOrder::query()
            ->where('status', 'paid')
            ->with([
                'items:id,order_id,product_name,product_id,product_type,quantity,unit_price',
                'counter:id,name',
            ])
            ->latest('created_at')
            ->get();
        $costs = (new PosOrderCostCalculator())->forOrders($orders);
        $categoryRecords = ExpenseCategory::withTrashed()->orderBy('name')->get();
        $activeCategories = $categoryRecords->whereNull('deleted_at')->values()
            ->map(fn (ExpenseCategory $category) => $this->serializeCategory($category));
        $allCategories = $categoryRecords
            ->map(fn (ExpenseCategory $category) => $this->serializeCategory($category));

        return view('pages.expenses', [
            'activeNav' => 'expenses',
            'expenseEntries' => Expense::query()->with('counter:id,name')
                ->select('id', 'expense_number', 'category', 'description', 'amount', 'expense_date', 'payment_method', 'status', 'counter_id', 'is_recurring', 'repeat_interval')
                ->orderByDesc('expense_date')->orderByDesc('id')->get()
                ->map(fn (Expense $expense) => $this->serializeExpense($expense)),
            'expenseSales' => $orders->map(fn (PosOrder $order) => [
                'date' => $order->created_at->toDateString(),
                'counterId' => $order->counter_id,
                'sales' => (float) $order->total,
                'cost' => $costs[$order->id] ?? 0,
            ])->values(),
            'categories' => $activeCategories,
            'expenseCategories' => $activeCategories,
            'expenseCategoryLabels' => $allCategories,
            'expenseCounters' => InventoryCounter::query()->orderBy('name')->get(['id', 'name']),
            'expensePaymentModes' => PosPaymentMode::query()->where('enabled', true)->orderBy('id')->pluck('name'),
            'expenseToday' => now()->toDateString(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $paymentModes = PosPaymentMode::query()->where('enabled', true)->pluck('name')->all();
        $data = $request->validate([
            'description' => ['required', 'string', 'max:180'],
            'category' => ['required', 'string', Rule::exists('expense_categories', 'slug')->whereNull('deleted_at')],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'payment_method' => ['nullable', 'string', 'max:120', Rule::in($paymentModes)],
            'expense_date' => ['required', 'date_format:Y-m-d'],
            'counter_id' => ['nullable', 'integer', 'exists:inventory_counters,id'],
            'is_recurring' => ['required', 'boolean'],
            'repeat_interval' => [
                Rule::requiredIf((bool) $request->input('is_recurring')),
                'nullable',
                Rule::in(self::REPEAT_INTERVALS),
            ],
        ]);

        $expense = DB::transaction(function () use ($data, $request) {
            $expense = Expense::query()->create([
                'expense_number' => 'TMP-'.Str::ulid(),
                'user_id' => $request->user()?->id,
                'counter_id' => $data['counter_id'] ?? null,
                'category' => $data['category'],
                'description' => trim($data['description']),
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'] ?? null,
                'expense_date' => $data['expense_date'],
                'status' => 'cleared',
                'is_recurring' => (bool) $data['is_recurring'],
                'repeat_interval' => $data['is_recurring'] ? $data['repeat_interval'] : null,
            ]);
            $expense->update([
                'expense_number' => 'EXP-'.str_pad((string) $expense->id, 6, '0', STR_PAD_LEFT),
            ]);

            return $expense->fresh('counter');
        }, 3);

        return response()->json([
            'ok' => true,
            'message' => 'Expense saved successfully.',
            'expense' => $this->serializeExpense($expense),
        ], 201);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:expense_categories,name'],
        ]);
        $baseSlug = Str::slug($data['name'], '_');
        if ($baseSlug === '') {
            return response()->json(['message' => 'Enter a category name containing letters or numbers.'], 422);
        }
        $slug = $baseSlug;
        $suffix = 2;
        while (ExpenseCategory::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'_'.$suffix++;
        }

        $category = ExpenseCategory::query()->create([
            'name' => trim($data['name']),
            'slug' => $slug,
        ]);

        return response()->json([
            'ok' => true,
            'category' => $this->serializeCategory($category),
        ], 201);
    }

    public function destroyCategory(ExpenseCategory $category): JsonResponse
    {
        $category->delete();

        return response()->json(['ok' => true]);
    }

    public function restoreCategory(int $category): JsonResponse
    {
        $record = ExpenseCategory::withTrashed()->findOrFail($category);
        $record->restore();

        return response()->json(['ok' => true, 'category' => $this->serializeCategory($record)]);
    }

    public function permanentlyDeleteCategory(int $category): JsonResponse
    {
        $record = ExpenseCategory::withTrashed()->findOrFail($category);
        if (Expense::withTrashed()->where('category', $record->slug)->exists()) {
            return response()->json([
                'ok' => false,
                'message' => 'This category is used by saved expenses and cannot be permanently deleted.',
            ], 422);
        }
        $record->forceDelete();

        return response()->json(['ok' => true]);
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $expense->delete();

        return response()->json(['ok' => true, 'message' => 'Expense moved to Settings trash.']);
    }

    public function restore(int $expense): JsonResponse
    {
        $record = Expense::withTrashed()->findOrFail($expense);
        $record->restore();

        return response()->json(['ok' => true]);
    }

    public function permanentlyDelete(int $expense): JsonResponse
    {
        Expense::withTrashed()->findOrFail($expense)->forceDelete();

        return response()->json(['ok' => true]);
    }

    private function serializeExpense(Expense $expense): array
    {
        return [
            'recordId' => $expense->id,
            'id' => $expense->expense_number,
            'category' => $expense->category,
            'description' => $expense->description,
            'amount' => (float) $expense->amount,
            'date' => $expense->expense_date->toDateString(),
            'paymentMethod' => $expense->payment_method ?? '',
            'status' => $expense->status,
            'counterId' => $expense->counter_id,
            'counter' => $expense->counter?->name ?? 'All Counters',
            'isRecurring' => (bool) $expense->is_recurring,
            'repeatInterval' => $expense->repeat_interval,
        ];
    }

    private function serializeCategory(ExpenseCategory $category): array
    {
        return [
            'id' => $category->slug,
            'recordId' => $category->id,
            'label' => $category->name,
        ];
    }
}
