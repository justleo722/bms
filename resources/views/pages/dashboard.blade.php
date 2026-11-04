@extends('layouts.master', ['activeNav' => $activeNav ?? 'dashboard'])

@section('title', 'Bar Dashboard')

@section('content')
@php
    $greeting = match (true) {
        now()->hour < 12 => 'Good morning',
        now()->hour < 17 => 'Good afternoon',
        default => 'Good evening',
    };
    $currencySymbol = \App\Settings\BarSettings::currencySymbol();
@endphp

<div class="pb-6">
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">Bar Dashboard</h1>
                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-emerald-600/20">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    {{ $dashboardIsOwner ? 'All Counters' : $dashboardCounterLabel }}
                </span>
            </div>
            <p class="mt-1 text-sm text-gray-500">{{ $greeting }}, {{ $dashboardUserName }}. Here's your {{ $dashboardIsOwner ? 'all-counter' : 'branch' }} overview for today.</p>
        </div>
        <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-600">
            @heroicon('o-calendar-days', 'h-4 w-4 text-gray-400')
            <span>{{ $dashboardToday }}</span>
        </div>
    </div>

    @if ($dashboardTodaySales !== null || $dashboardTodayProfit !== null || $dashboardTodayExpenses !== null)
    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @if ($dashboardTodaySales !== null)
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-gray-500">Today's Sales</p>
                    <p class="mt-2 text-2xl font-bold tracking-tight text-gray-900">{{ $currencySymbol }} {{ number_format($dashboardTodaySales) }}</p>
                    <p class="mt-1 text-xs text-gray-400">Paid orders today</p>
                </div>
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-green-50 text-green-600">@heroicon('o-currency-dollar', 'h-5 w-5')</span>
            </div>
        </div>
        @endif
        @if ($dashboardTodayProfit !== null)
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-gray-500">Today's Estimated Profit</p>
                    <p class="mt-2 text-2xl font-bold tracking-tight text-gray-900">{{ $currencySymbol }} {{ number_format($dashboardTodayProfit) }}</p>
                    <p class="mt-1 text-xs text-gray-400">After estimated COGS and expenses</p>
                </div>
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">@heroicon('o-arrow-trending-up', 'h-5 w-5')</span>
            </div>
        </div>
        @endif
        @if ($dashboardTodayExpenses !== null)
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-gray-500">Today's Expenses</p>
                    <p class="mt-2 text-2xl font-bold tracking-tight text-gray-900">{{ $currencySymbol }} {{ number_format($dashboardTodayExpenses) }}</p>
                    <p class="mt-1 text-xs text-gray-400">{{ $dashboardExpenseCount }} expense entries</p>
                </div>
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-red-50 text-red-600">@heroicon('o-banknotes', 'h-5 w-5')</span>
            </div>
        </div>
        @endif
    </div>
    @endif

    @if ($dashboardCanQuickActions)
        @php
            $quickActions = [
                ['allowed' => $dashboardCanOpenPos, 'href' => '/pos', 'title' => 'New Sale', 'description' => 'Open POS terminal', 'icon' => 'pos', 'tone' => 'bg-amber-50 text-amber-600'],
                ['allowed' => $dashboardCanAddExpense, 'href' => '/expenses', 'title' => 'Add Expense', 'description' => 'Record a business cost', 'icon' => 'expense', 'tone' => 'bg-green-50 text-green-600'],
                ['allowed' => $dashboardCanOpenInventory, 'href' => '/inventory', 'title' => 'Stock Check', 'description' => 'View inventory levels', 'icon' => 'inventory', 'tone' => 'bg-purple-50 text-purple-600'],
                ['allowed' => $dashboardCanOpenKitchen, 'href' => '/kitchen', 'title' => 'Kitchen', 'description' => 'View kitchen operations', 'icon' => 'kitchen', 'tone' => 'bg-blue-50 text-blue-600'],
            ];
            $quickActions = array_values(array_filter($quickActions, fn ($action) => $action['allowed']));
        @endphp
        @if (count($quickActions))
        <section class="mb-8">
            <h2 class="mb-4 text-lg font-semibold text-gray-900">Quick Actions</h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ($quickActions as $action)
                    <a href="{{ $action['href'] }}" class="group rounded-xl border border-gray-200 bg-white p-4 text-center shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:p-5">
                        <span class="mx-auto mb-3 flex h-11 w-11 items-center justify-center rounded-xl {{ $action['tone'] }} transition group-hover:scale-110">
                            @if ($action['icon'] === 'pos')
                                @heroicon('o-plus-circle', 'h-5 w-5')
                            @elseif ($action['icon'] === 'expense')
                                @heroicon('o-banknotes', 'h-5 w-5')
                            @elseif ($action['icon'] === 'inventory')
                                @heroicon('o-cube', 'h-5 w-5')
                            @else
                                @heroicon('o-fire', 'h-5 w-5')
                            @endif
                        </span>
                        <span class="block text-sm font-semibold text-gray-900">{{ $action['title'] }}</span>
                        <span class="mt-1 block text-xs text-gray-500">{{ $action['description'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>
        @endif
    @endif

    <div class="grid grid-cols-1 gap-6 {{ $dashboardCanViewSales && $dashboardCanViewStock ? 'lg:grid-cols-3' : 'lg:grid-cols-2' }}">
        @if ($dashboardCanViewSales)
        <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-gray-900">Top-Selling Items Today</h2>
                <span class="text-xs text-gray-400">By quantity</span>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse ($dashboardTopSellingItems as $index => $item)
                    <div class="flex items-center gap-3 px-5 py-3">
                        <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg bg-gray-100 text-xs font-bold text-gray-500">{{ $index + 1 }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ $item['name'] }}</p>
                            <p class="text-xs text-gray-500">{{ number_format($item['quantity']) }} sold</p>
                        </div>
                        <p class="text-right text-sm font-semibold text-gray-900">{{ $currencySymbol }} {{ number_format($item['revenue']) }}</p>
                    </div>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-gray-400">No paid sales have been recorded today.</p>
                @endforelse
            </div>
            @if (auth()->user()->hasPermission('sales'))
                <div class="border-t border-gray-100 px-5 py-3"><a href="/sales" class="text-sm font-medium text-blue-600 hover:text-blue-700">View sales &rarr;</a></div>
            @endif
        </section>
        @endif

        @if ($dashboardCanViewStock)
        <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-gray-900">Stock Alerts</h2>
                <span class="text-xs text-gray-400">At or below reorder level</span>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse ($dashboardStockAlerts as $alert)
                    @php($outOfStock = $alert['quantity'] <= 0)
                    <div class="flex items-center gap-3 px-5 py-3">
                        <span class="h-2 w-2 flex-shrink-0 rounded-full {{ $outOfStock ? 'bg-red-500' : 'bg-amber-500' }}"></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ $alert['item'] }}</p>
                            <p class="text-xs text-gray-500">{{ $alert['counter'] }}</p>
                        </div>
                        <span class="rounded-full px-2 py-1 text-[10px] font-medium {{ $outOfStock ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700' }}">
                            {{ $outOfStock ? 'Out of stock' : number_format($alert['quantity'], 3).' '.$alert['unit'].' left' }}
                        </span>
                    </div>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-gray-400">No low-stock items need attention.</p>
                @endforelse
            </div>
            <div class="border-t border-gray-100 px-5 py-3"><a href="/inventory" class="text-sm font-medium text-blue-600 hover:text-blue-700">View inventory &rarr;</a></div>
        </section>
        @endif

        @if ($dashboardCanViewSales)
        <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-gray-900">Recent Sales Today</h2>
                <span class="text-xs text-gray-400">Latest 5</span>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse ($dashboardRecentSales as $sale)
                    <div class="flex items-center gap-3 px-5 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-x-2 text-sm font-semibold text-gray-900">
                                <span>{{ $sale['id'] }}</span><span class="text-xs font-normal text-gray-400">{{ $sale['counter'] }}</span>
                            </p>
                            <p class="mt-0.5 text-xs text-gray-500">{{ $sale['items'] }} items · {{ $sale['method'] }} · {{ $sale['time'] }}</p>
                        </div>
                        <p class="text-right text-sm font-semibold text-gray-900">{{ $currencySymbol }} {{ number_format($sale['total']) }}</p>
                    </div>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-gray-400">No paid sales have been recorded today.</p>
                @endforelse
            </div>
            @if (auth()->user()->hasPermission('sales'))
                <div class="border-t border-gray-100 px-5 py-3"><a href="/sales" class="text-sm font-medium text-blue-600 hover:text-blue-700">View all sales &rarr;</a></div>
            @endif
        </section>
        @endif
    </div>

    @if ($dashboardCanViewExpenses)
    <section class="mt-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-gray-900">Recent Expenses Today</h2>
            @if (auth()->user()->hasPermission('expenses'))
                <a href="/expenses" class="text-sm font-medium text-blue-600 hover:text-blue-700">View expenses</a>
            @endif
        </div>
        <div class="scroll-x overflow-x-auto">
            <table class="w-full min-w-[600px] text-sm">
                <thead class="bg-gray-50/70">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Description</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Category</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Method</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Amount</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($dashboardRecentExpenses as $expense)
                        <tr>
                            <td class="px-5 py-3 font-medium text-gray-900">{{ $expense['description'] }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $expense['category'] }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $expense['method'] }}</td>
                            <td class="px-5 py-3 text-right font-semibold text-gray-900">{{ $currencySymbol }} {{ number_format($expense['amount']) }}</td>
                            <td class="px-5 py-3 text-right text-xs text-gray-400">{{ $expense['time'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-sm text-gray-400">No expenses have been recorded today.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    @endif
</div>
@endsection
