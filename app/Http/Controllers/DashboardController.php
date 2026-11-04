<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryCounter;
use App\Models\InventoryStock;
use App\Models\PosOrder;
use App\Models\User;
use App\Support\PosOrderCostCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_if($user->status === 'inactive', 403, 'This staff account is inactive.');
        if (! $user->hasPermission('dashboard')) {
            return redirect($user->landingPath());
        }

        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $counterId = $user->isAdministrator() ? null : $user->counter_id;

        $canViewSales = $user->hasPermission('dashboard', 'todaySales');
        $canViewProfit = $user->hasPermission('dashboard', 'todayProfit');
        $canViewExpenses = $user->hasPermission('dashboard', 'todayExpenses');
        $canViewQuickActions = $user->hasPermission('dashboard', 'quickActions');
        $canViewStock = $user->isAdministrator()
            || (bool) data_get($user->permissions, 'inventory.enabled', false);

        $orders = $canViewSales || $canViewProfit
            ? $this->todayOrders($counterId, $todayStart, $todayEnd)->with(['items', 'counter'])->get()
            : collect();
        $expenses = $canViewExpenses || $canViewProfit
            ? $this->todayExpenses($counterId, $todayStart, $todayEnd)->get()
            : collect();
        $orderCosts = $canViewProfit
            ? (new PosOrderCostCalculator())->forOrders($orders)
            : [];

        $todaySales = (float) $orders->sum(fn (PosOrder $order) => (float) $order->total);
        $todayCost = array_sum($orderCosts);
        $todayExpenseTotal = (float) $expenses->sum(fn (Expense $expense) => (float) $expense->amount);
        $pnlExpenses = $expenses->reject(fn (Expense $expense) => in_array($expense->category, ['inventory'], true));
        $todayProfit = $todaySales - $todayCost - (float) $pnlExpenses->sum(fn (Expense $expense) => (float) $expense->amount);

        $topSellingItems = $canViewSales
            ? DB::table('pos_order_items')
                ->join('pos_orders', 'pos_orders.id', '=', 'pos_order_items.order_id')
                ->where('pos_orders.status', 'paid')
                ->whereBetween('pos_orders.created_at', [$todayStart, $todayEnd])
                ->when($counterId !== null, fn ($query) => $query->where('pos_orders.counter_id', $counterId))
                ->select('pos_order_items.product_name')
                ->selectRaw('SUM(pos_order_items.quantity) as quantity_sold, SUM(pos_order_items.line_total) as revenue')
                ->groupBy('pos_order_items.product_name')
                ->orderByDesc('quantity_sold')
                ->limit(6)
                ->get()
                ->map(fn ($item) => [
                    'name' => $item->product_name,
                    'quantity' => (int) $item->quantity_sold,
                    'revenue' => (float) $item->revenue,
                ])
            : collect();

        $stockAlerts = $canViewStock
            ? InventoryStock::query()
                ->with(['product', 'counter'])
                ->whereHas('product')
                ->where('reorder_level', '>', 0)
                ->whereColumn('quantity', '<=', 'reorder_level')
                ->when($counterId !== null, fn (Builder $query) => $query->where('counter_id', $counterId))
                ->orderByRaw('CASE WHEN quantity = 0 THEN 0 ELSE 1 END')
                ->orderBy('quantity')
                ->limit(6)
                ->get()
                ->map(fn (InventoryStock $stock) => [
                    'item' => $stock->product->name,
                    'counter' => $stock->counter->name,
                    'quantity' => (float) $stock->quantity,
                    'reorderLevel' => (float) $stock->reorder_level,
                    'unit' => $stock->product->unit,
                ])
            : collect();

        $recentSales = $canViewSales
            ? $orders->sortByDesc('created_at')->take(5)->map(fn (PosOrder $order) => [
                'id' => $order->docket_number,
                'counter' => $order->counter?->name ?? '',
                'items' => (int) $order->items->sum('quantity'),
                'total' => (float) $order->total,
                'time' => $order->created_at->format('g:i A'),
                'method' => $order->payment_method ?: '—',
            ])->values()
            : collect();

        $recentExpenses = $canViewExpenses
            ? $expenses->sortByDesc('created_at')->take(5)->values()
            : collect();
        $categoryLabels = ExpenseCategory::withTrashed()->pluck('name', 'slug');
        $recentExpenses = $recentExpenses->map(fn (Expense $expense) => [
            'description' => $expense->description,
            'category' => $categoryLabels->get($expense->category, str($expense->category)->replace('_', ' ')->title()),
            'method' => $expense->payment_method ?: '—',
            'amount' => (float) $expense->amount,
            'time' => $expense->created_at->format('g:i A'),
        ]);

        $counter = $counterId ? InventoryCounter::query()->find($counterId, ['id', 'name']) : null;

        return view('pages.dashboard', [
            'activeNav' => 'dashboard',
            'dashboardIsOwner' => $user->isAdministrator(),
            'dashboardUserName' => str($user->name)->before(' ')->toString(),
            'dashboardCounterLabel' => $counter?->name ?? 'All Counters',
            'dashboardToday' => now()->format('l, F j, Y'),
            'dashboardTodaySales' => $canViewSales ? $todaySales : null,
            'dashboardTodayProfit' => $canViewProfit ? $todayProfit : null,
            'dashboardTodayExpenses' => $canViewExpenses ? $todayExpenseTotal : null,
            'dashboardExpenseCount' => $canViewExpenses ? $expenses->count() : null,
            'dashboardTopSellingItems' => $topSellingItems,
            'dashboardStockAlerts' => $stockAlerts,
            'dashboardRecentSales' => $recentSales,
            'dashboardRecentExpenses' => $recentExpenses,
            'dashboardCanViewSales' => $canViewSales,
            'dashboardCanViewExpenses' => $canViewExpenses,
            'dashboardCanViewStock' => $canViewStock,
            'dashboardCanQuickActions' => $canViewQuickActions,
            'dashboardCanOpenPos' => $user->hasPermission('pos'),
            'dashboardCanAddExpense' => $user->hasPermission('expenses', 'add'),
            'dashboardCanOpenInventory' => $user->hasPermission('inventory'),
            'dashboardCanOpenKitchen' => $user->hasPermission('kitchen'),
        ]);
    }

    private function todayOrders(?int $counterId, $start, $end): Builder
    {
        return PosOrder::query()
            ->where('status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->when($counterId !== null, fn (Builder $query) => $query->where('counter_id', $counterId));
    }

    private function todayExpenses(?int $counterId, $start, $end): Builder
    {
        return Expense::query()
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->when($counterId !== null, fn (Builder $query) => $query->where(fn (Builder $branch) => $branch
                ->where('counter_id', $counterId)
                ->orWhereNull('counter_id')));
    }
}
