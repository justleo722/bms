@extends('layouts.master', ['activeNav' => $activeNav ?? 'expenses'])

@section('title', 'Expenses')

@php
    $expenseUser = auth()->user();
    $canViewExpenses = $expenseUser?->hasPermission('expenses', 'view') ?? false;
    $canAddExpense = $expenseUser?->hasPermission('expenses', 'add') ?? false;
    $canDeleteExpense = $expenseUser?->hasPermission('expenses', 'delete') ?? false;
@endphp

@push('scripts')
<script>
window.expensesData = function (expenses, sales, categories, categoryLabels, counters, paymentModes, todayDate) {
    return {
        recentExpenses: Array.isArray(expenses) ? expenses : [],
        sales: Array.isArray(sales) ? sales : [],
        categories: categories || [],
        categoryLabels: categoryLabels || categories || [],
        counters: counters || [],
        paymentModes: paymentModes || [],
        todayDate: todayDate,
        activeCategory: 'all',
        showAddModal: false,
        showDeleteModal: false,
        expenseToDelete: null,
        dateFilter: 'today',
        dateFrom: '',
        dateTo: '',
        counterFilter: 'all',
        showBreakdownFilterModal: false,
        breakdownDateFilter: 'today',
        breakdownDateFrom: '',
        breakdownDateTo: '',
        breakdownCounter: 'all',
        draftBreakdownDateFilter: 'today',
        draftBreakdownDateFrom: '',
        draftBreakdownDateTo: '',
        draftBreakdownCounter: 'all',
        recurringEnabled: false,
        newExpenseDesc: '',
        newExpenseCategory: 'rent',
        newExpenseAmount: '',
        newExpenseMethod: '',
        newExpenseDate: todayDate,
        newExpenseCounter: '',
        newExpenseRepeat: 'Monthly',
        actionSaving: false,
        actionError: '',

        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },

        dateMatches(value, filter, from, to) {
            var date = (value || '').slice(0, 10);
            if (!date) return false;
            filter = filter || this.dateFilter;
            from = from === undefined ? this.dateFrom : from;
            to = to === undefined ? this.dateTo : to;
            if (filter === 'all') return true;
            if (filter === 'today') return date === this.todayDate;
            if (filter === 'yesterday') {
                var yesterday = new Date(this.todayDate + 'T00:00:00');
                yesterday.setDate(yesterday.getDate() - 1);
                return date === this.isoDate(yesterday);
            }
            var current = new Date(this.todayDate + 'T00:00:00');
            var valueDate = new Date(date + 'T00:00:00');
            if (filter === 'thisWeek' || filter === 'lastWeek') {
                var weekStart = new Date(current);
                weekStart.setDate(current.getDate() - ((current.getDay() + 6) % 7));
                var weekEnd = new Date(weekStart);
                weekEnd.setDate(weekStart.getDate() + 6);
                if (filter === 'lastWeek') {
                    weekStart.setDate(weekStart.getDate() - 7);
                    weekEnd.setDate(weekEnd.getDate() - 7);
                }
                return valueDate >= weekStart && valueDate <= weekEnd;
            }
            if (filter === 'thisMonth') return date.slice(0, 7) === this.todayDate.slice(0, 7);
            if (filter === 'lastMonth') {
                var lastMonth = new Date(current.getFullYear(), current.getMonth() - 1, 1);
                return date.slice(0, 7) === lastMonth.getFullYear() + '-' + String(lastMonth.getMonth() + 1).padStart(2, '0');
            }
            if (filter === 'thisYear') return date.slice(0, 4) === this.todayDate.slice(0, 4);
            if (filter === 'custom') return (!from || date >= from) && (!to || date <= to);
            return true;
        },

        isoDate(date) {
            return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
        },

        get periodExpenses() {
            var self = this;
            return this.recentExpenses.filter(function (expense) {
                var counterMatches = self.counterFilter === 'all' || expense.counterId === null || String(expense.counterId) === String(self.counterFilter);
                return self.dateMatches(expense.date) && counterMatches;
            });
        },

        get filteredExpenses() {
            var category = this.activeCategory;
            return this.periodExpenses.filter(function (expense) { return category === 'all' || expense.category === category; });
        },

        get periodSales() {
            var self = this;
            return this.sales.filter(function (sale) {
                var counterMatches = self.counterFilter === 'all' || String(sale.counterId) === String(self.counterFilter);
                return self.dateMatches(sale.date) && counterMatches;
            });
        },

        get breakdownExpenses() {
            var self = this;
            return this.recentExpenses.filter(function (expense) {
                var counterMatches = self.breakdownCounter === 'all'
                    || expense.counterId === null
                    || String(expense.counterId) === String(self.breakdownCounter);
                return self.dateMatches(expense.date, self.breakdownDateFilter, self.breakdownDateFrom, self.breakdownDateTo)
                    && counterMatches;
            });
        },

        get breakdownSales() {
            var self = this;
            return this.sales.filter(function (sale) {
                var counterMatches = self.breakdownCounter === 'all' || String(sale.counterId) === String(self.breakdownCounter);
                return self.dateMatches(sale.date, self.breakdownDateFilter, self.breakdownDateFrom, self.breakdownDateTo)
                    && counterMatches;
            });
        },

        get breakdown() {
            var revenue = this.breakdownSales.reduce(function (sum, sale) { return sum + sale.sales; }, 0);
            var cogs = this.breakdownSales.reduce(function (sum, sale) { return sum + sale.cost; }, 0);
            var expenses = this.breakdownExpenses;
            var operatingExpenses = expenses.filter(function (expense) { return expense.category !== 'inventory' && expense.category !== 'bad_wastage'; })
                .reduce(function (sum, expense) { return sum + expense.amount; }, 0);
            var badWastage = expenses.filter(function (expense) { return expense.category === 'bad_wastage'; })
                .reduce(function (sum, expense) { return sum + expense.amount; }, 0);
            var inventoryPurchases = expenses.filter(function (expense) { return expense.category === 'inventory'; })
                .reduce(function (sum, expense) { return sum + expense.amount; }, 0);
            var grossProfit = revenue - cogs;
            return { sales: revenue, cogs: cogs, grossProfit: grossProfit, expenses: operatingExpenses, badWastage: badWastage,
                inventoryPurchases: inventoryPurchases, netProfit: grossProfit - operatingExpenses - badWastage };
        },

        get totals() {
            return { expenses: this.periodExpenses.reduce(function (sum, expense) { return sum + expense.amount; }, 0), netProfit: this.breakdown.netProfit };
        },

        formatMoney(value) { return (Number(value) || 0).toLocaleString(); },
        categoryLabel(id) {
            var category = this.categoryLabels.find(function (item) { return item.id === id; });
            return category ? category.label : id;
        },
        counterLabel() {
            if (this.counterFilter === 'all') return 'All counters';
            var counter = this.counters.find((item) => String(item.id) === String(this.counterFilter));
            return counter ? counter.name : 'Selected counter';
        },
        breakdownCounterLabel() {
            if (this.breakdownCounter === 'all') return 'All counters';
            var counter = this.counters.find((item) => String(item.id) === String(this.breakdownCounter));
            return counter ? counter.name : 'Selected counter';
        },
        breakdownDateLabel() {
            var labels = {
                today: 'Today', yesterday: 'Yesterday', thisWeek: 'This Week', lastWeek: 'Last Week',
                thisMonth: 'This Month', lastMonth: 'Last Month', thisYear: 'This Year', all: 'All Time',
            };
            if (this.breakdownDateFilter === 'custom') {
                return (this.breakdownDateFrom || 'Any date') + ' to ' + (this.breakdownDateTo || 'Any date');
            }
            return labels[this.breakdownDateFilter] || 'Selected date range';
        },

        openAddExpense() {
            this.newExpenseDesc = '';
            var firstCategory = this.categories.find(function (category) { return category.id !== 'all'; });
            this.newExpenseCategory = firstCategory ? firstCategory.id : 'rent';
            this.newExpenseAmount = '';
            this.newExpenseMethod = this.paymentModes[0] || '';
            this.newExpenseDate = this.todayDate;
            this.newExpenseCounter = '';
            this.recurringEnabled = false;
            this.newExpenseRepeat = 'Monthly';
            this.actionError = '';
            this.showAddModal = true;
        },

        openBreakdownFilters() {
            this.draftBreakdownDateFilter = this.breakdownDateFilter;
            this.draftBreakdownDateFrom = this.breakdownDateFrom;
            this.draftBreakdownDateTo = this.breakdownDateTo;
            this.draftBreakdownCounter = this.breakdownCounter;
            this.showBreakdownFilterModal = true;
        },

        applyBreakdownFilters() {
            this.breakdownDateFilter = this.draftBreakdownDateFilter;
            this.breakdownDateFrom = this.draftBreakdownDateFrom;
            this.breakdownDateTo = this.draftBreakdownDateTo;
            this.breakdownCounter = this.draftBreakdownCounter;
            this.showBreakdownFilterModal = false;
        },

        askDeleteExpense(expense) {
            this.expenseToDelete = expense;
            this.actionError = '';
            this.showDeleteModal = true;
        },

        confirmDeleteExpense() {
            if (!this.expenseToDelete || this.actionSaving) return;
            var self = this;
            var expense = this.expenseToDelete;
            this.actionSaving = true;
            this.actionError = '';
            window.axios.delete('/expenses/' + expense.recordId, { headers: { 'X-CSRF-TOKEN': this.csrfToken() } })
                .then(function () {
                    self.recentExpenses = self.recentExpenses.filter(function (item) { return item.recordId !== expense.recordId; });
                    self.showDeleteModal = false;
                    self.expenseToDelete = null;
                }).catch(function (error) {
                    self.actionError = error.response?.data?.message || 'Could not move this expense to trash.';
                }).then(function () { self.actionSaving = false; });
        },

        addExpense() {
            if (this.actionSaving) return;
            var self = this;
            this.actionSaving = true;
            this.actionError = '';
            window.axios.post('/expenses', {
                description: this.newExpenseDesc,
                category: this.newExpenseCategory,
                amount: Number(this.newExpenseAmount),
                payment_method: this.newExpenseMethod || null,
                expense_date: this.newExpenseDate || this.todayDate,
                counter_id: this.newExpenseCounter || null,
                is_recurring: this.recurringEnabled,
                repeat_interval: this.recurringEnabled ? this.newExpenseRepeat : null,
            }, { headers: { 'X-CSRF-TOKEN': this.csrfToken() } })
                .then(function (response) {
                    self.recentExpenses.unshift(response.data.expense);
                    self.showAddModal = false;
                }).catch(function (error) {
                    self.actionError = error.response?.data?.message || Object.values(error.response?.data?.errors || {}).flat()[0] || 'Could not save this expense.';
                }).then(function () { self.actionSaving = false; });
        },
    };
};
</script>
@endpush

@section('content')
<div x-data="expensesData(@js($canViewExpenses ? $expenseEntries : []), @js($canViewExpenses ? $expenseSales : []), @js($expenseCategories), @js($expenseCategoryLabels), @js($expenseCounters), @js($expensePaymentModes), @js($expenseToday))" class="pb-6">
    {{-- Header --}}
    <div class="mb-5 flex flex-col gap-3 sm:mb-6 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-xl font-bold text-gray-900 tracking-tight sm:text-2xl">Profit &amp; Expenses</h1>
            <p class="mt-1 text-sm text-gray-500">Database-backed expenses and profit &amp; loss using saved POS sales.</p>
        </div>
        @if ($canAddExpense)
        <div class="flex items-center gap-3">
            <x-button variant="primary" size="sm" icon="o-plus" class="w-full sm:w-auto" @click="openAddExpense()">Add Expense</x-button>
        </div>
        @endif
    </div>

    @if (! $canViewExpenses)
        <div class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            You can add expenses, but viewing expense records and profit breakdowns is not enabled for your account.
        </div>
    @else
    {{-- Summary Cards --}}
    <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-gray-500">Expenses (Filtered)</p>
                    <p class="mt-1.5 text-xl font-bold tracking-tight text-gray-900 sm:text-2xl"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(totals.expenses)"></span></p>
                    <div class="mt-1.5 flex items-center gap-1.5">
                        <span class="text-xs text-gray-400 sm:text-sm">for the selected date and counter</span>
                    </div>
                </div>
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-600 sm:h-12 sm:w-12">
                    @heroicon('o-banknotes', 'w-6 h-6')
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-gray-500">Net Profit</p>
                    <p class="mt-1.5 text-xl font-bold tracking-tight text-gray-900 sm:text-2xl"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(totals.netProfit)"></span></p>
                    <div class="mt-1.5 flex items-center gap-1.5">
                        <span class="inline-flex items-center gap-0.5 text-sm font-medium text-green-600">
                            @heroicon('o-arrow-trending-up', 'w-4 h-4')
                        </span>
                        <span class="text-xs text-gray-400 sm:text-sm">after estimated COGS and expenses</span>
                    </div>
                </div>
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 sm:h-12 sm:w-12">
                    @heroicon('o-arrow-trending-up', 'w-6 h-6')
                </div>
            </div>
        </div>
    </div>

    {{-- P&L Breakdown --}}
    <div class="mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h3 class="text-sm font-semibold text-gray-900">Breakdown</h3>
                <p class="text-xs text-gray-400" x-text="breakdownDateLabel() + ' · ' + breakdownCounterLabel()"></p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="openBreakdownFilters()" class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 sm:w-auto">
                    @heroicon('o-funnel', 'w-3.5 h-3.5')
                    Filter breakdown
                </button>
            </div>
        </div>
        <div class="space-y-1 sm:space-y-3">
            <div class="flex items-start justify-between gap-3 border-b border-gray-100 py-2">
                <span class="min-w-0 text-sm font-medium text-gray-600">Revenue</span>
                <span class="flex-shrink-0 whitespace-nowrap text-sm font-semibold text-gray-900"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(breakdown.sales)"></span></span>
            </div>
            <div class="flex items-start justify-between gap-3 border-b border-gray-100 py-2">
                <span class="min-w-0 text-sm font-medium text-gray-600">Cost of Goods Sold (estimated)</span>
                <span class="flex-shrink-0 whitespace-nowrap text-sm font-semibold text-gray-900"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(breakdown.cogs)"></span></span>
            </div>
            <div class="flex items-start justify-between gap-3 border-b border-gray-100 py-2">
                <span class="min-w-0 text-sm font-medium text-gray-600">Gross Profit</span>
                <span class="flex-shrink-0 whitespace-nowrap text-sm font-semibold text-gray-900"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(breakdown.grossProfit)"></span></span>
            </div>
            <div class="flex items-start justify-between gap-3 border-b border-gray-100 py-2">
                <span class="min-w-0 text-sm font-medium text-gray-600">Operating Expenses</span>
                <span class="flex-shrink-0 whitespace-nowrap text-sm font-semibold text-red-600"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(breakdown.expenses)"></span></span>
            </div>
            <div class="flex items-start justify-between gap-3 border-b border-gray-100 py-2">
                <span class="min-w-0 text-sm font-medium text-gray-600">Inventory Purchases (excluded from P&amp;L)</span>
                <span class="flex-shrink-0 whitespace-nowrap text-sm font-semibold text-gray-700"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(breakdown.inventoryPurchases)"></span></span>
            </div>
            <div class="flex items-start justify-between gap-3 border-b border-gray-100 py-2">
                <span class="min-w-0 text-sm font-medium text-gray-600">Bad/Wastage/Expired</span>
                <span class="flex-shrink-0 whitespace-nowrap text-sm font-semibold text-amber-600"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(breakdown.badWastage)"></span></span>
            </div>
            <div class="flex items-start justify-between gap-3 py-2">
                <span class="min-w-0 text-sm font-semibold text-gray-900">Net Profit</span>
                <span class="flex-shrink-0 whitespace-nowrap text-sm font-bold text-emerald-600"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(breakdown.netProfit)"></span></span>
            </div>
        </div>
        <p class="mt-4 border-t border-gray-100 pt-3 text-xs text-gray-400">
            COGS uses current product and recipe costs because historical cost snapshots are not stored.
            Inventory purchase expenses are displayed separately to avoid counting the same stock cost twice.
        </p>
    </div>

    {{-- Filters --}}
    <div class="mb-4 flex flex-col gap-3">
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            <select x-model="dateFilter" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm outline-none focus:border-blue-500 sm:h-9 sm:w-auto">
                <option value="today">Today</option>
                <option value="yesterday">Yesterday</option>
                <option value="thisWeek">This Week</option>
                <option value="thisMonth">This Month</option>
                <option value="lastMonth">Last Month</option>
                <option value="all">All Time</option>
                <option value="custom">Custom Range</option>
            </select>
            <template x-if="dateFilter === 'custom'">
                <div class="grid grid-cols-2 gap-2 sm:flex sm:items-center">
                    <input type="date" x-model="dateFrom" aria-label="From date" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm outline-none focus:border-blue-500 sm:h-9 sm:w-auto">
                    <span class="hidden text-xs text-gray-400 sm:inline">to</span>
                    <input type="date" x-model="dateTo" aria-label="To date" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm outline-none focus:border-blue-500 sm:h-9 sm:w-auto">
                </div>
            </template>
            <select x-model="counterFilter" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm outline-none focus:border-blue-500 sm:h-9 sm:w-auto">
                <option value="all">All Counters</option>
                <template x-for="c in counters" :key="c.id">
                    <option :value="c.id" x-text="c.name"></option>
                </template>
            </select>
        </div>
        <div class="scroll-x -mx-1 flex items-center gap-2 overflow-x-auto px-1 pb-1 no-scrollbar">
            <button @click="activeCategory = 'all'"
                    :class="activeCategory === 'all' ? 'bg-blue-600 text-white shadow-sm shadow-blue-200' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'"
                    class="flex flex-shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg px-3 py-1.5 text-xs font-medium transition-all">
                All Categories
            </button>
            @foreach ($categories as $cat)
                <button @click="activeCategory = '{{ $cat['id'] }}'"
                        :class="activeCategory === '{{ $cat['id'] }}' ? 'bg-blue-600 text-white shadow-sm shadow-blue-200' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'"
                        class="flex flex-shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg px-3 py-1.5 text-xs font-medium transition-all">
                    {{ $cat['label'] }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Independent P&L breakdown filters --}}
    <template x-teleport="body">
        <div x-show="showBreakdownFilterModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showBreakdownFilterModal = false"></div>
            <div class="relative z-10 w-full max-w-lg max-h-[90dvh] overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-4 sm:px-6">
                    <h3 class="text-lg font-bold text-gray-900">Filter Breakdown</h3>
                    <button type="button" @click="showBreakdownFilterModal = false" class="p-1 text-gray-400 hover:text-gray-600" aria-label="Close filters">@heroicon('o-x-mark', 'w-5 h-5')</button>
                </div>
                <div class="space-y-4 px-4 py-5 sm:px-6">
                    <label class="block text-sm font-medium text-gray-700">Date range
                        <select x-model="draftBreakdownDateFilter" class="mt-1 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm">
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="thisWeek">This Week</option>
                            <option value="lastWeek">Last Week</option>
                            <option value="thisMonth">This Month</option>
                            <option value="lastMonth">Last Month</option>
                            <option value="thisYear">This Year</option>
                            <option value="all">All Time</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </label>
                    <div x-show="draftBreakdownDateFilter === 'custom'" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="block text-sm font-medium text-gray-700">From
                            <input type="date" x-model="draftBreakdownDateFrom" class="mt-1 h-10 w-full rounded-lg border border-gray-300 px-3 text-sm">
                        </label>
                        <label class="block text-sm font-medium text-gray-700">To
                            <input type="date" x-model="draftBreakdownDateTo" class="mt-1 h-10 w-full rounded-lg border border-gray-300 px-3 text-sm">
                        </label>
                    </div>
                    <label class="block text-sm font-medium text-gray-700">Counter
                        <select x-model="draftBreakdownCounter" class="mt-1 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm">
                            <option value="all">All Counters</option>
                            <template x-for="counter in counters" :key="counter.id">
                                <option :value="counter.id" x-text="counter.name"></option>
                            </template>
                        </select>
                    </label>
                </div>
                <div class="flex gap-2 border-t border-gray-200 bg-gray-50 px-4 py-4 sm:px-6">
                    <button type="button" @click="showBreakdownFilterModal = false" class="flex-1 rounded-xl border border-gray-300 py-2.5 text-sm font-medium text-gray-700 hover:bg-white">Cancel</button>
                    <button type="button" @click="applyBreakdownFilters()" class="flex-1 rounded-xl bg-blue-600 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Apply Filters</button>
                </div>
            </div>
        </div>
    </template>

    {{-- Expenses Table --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <p class="px-4 pt-3 text-[11px] text-gray-400 sm:hidden">Swipe sideways to see all columns.</p>
        <div class="scroll-x overflow-x-auto">
            <table class="w-full min-w-[860px] text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/50">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Description</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Counter</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Method</th>
                        <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        @if ($canDeleteExpense)
                        <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <template x-for="expense in filteredExpenses" :key="expense.recordId">
                        <tr class="hover:bg-gray-50/40 transition-colors">
                            <td class="px-5 py-3 text-gray-400 font-medium" x-text="expense.id"></td>
                            <td class="px-5 py-3 font-medium text-gray-900" x-text="expense.description"></td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-600" x-text="categoryLabel(expense.category)"></span>
                            </td>
                            <td class="px-5 py-3 text-gray-600" x-text="expense.counter"></td>
                            <td class="px-5 py-3 text-gray-600" x-text="expense.paymentMethod"></td>
                            <td class="px-5 py-3 text-right font-semibold text-gray-900"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(expense.amount)"></span></td>
                            <td class="px-5 py-3 text-gray-500" x-text="expense.date"></td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium"
                                      :class="expense.status === 'cleared' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
                                      x-text="expense.status.charAt(0).toUpperCase() + expense.status.slice(1)"></span>
                                <span x-show="expense.isRecurring" class="ml-1 inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-medium text-blue-700" x-text="expense.repeatInterval"></span>
                            </td>
                            @if ($canDeleteExpense)
                            <td class="px-5 py-3 text-right">
                                <button type="button" @click="askDeleteExpense(expense)" :aria-label="'Move ' + expense.description + ' to trash'"
                                        class="inline-flex items-center justify-center p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                    @heroicon('o-trash', 'w-4 h-4')
                                </button>
                            </td>
                            @endif
                        </tr>
                    </template>
                    <tr x-show="filteredExpenses.length === 0" x-cloak>
                        <td colspan="{{ $canDeleteExpense ? 9 : 8 }}" class="px-5 py-12 text-center text-sm text-gray-500">
                            No expenses match the selected filters.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Delete Expense Confirmation --}}
    @if ($canDeleteExpense)
    <template x-teleport="body">
        <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
             @click.away="showDeleteModal = false" @keydown.escape.window="showDeleteModal = false">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showDeleteModal = false"></div>
            <div class="relative z-10 max-h-[90dvh] w-full max-w-sm overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div class="px-5 py-5 text-center sm:px-6">
                    <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-red-100 text-red-600 mb-4">
                        @heroicon('o-trash', 'w-7 h-7')
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Move Expense to Trash?</h3>
                    <p class="text-sm text-gray-500">This expense can be restored from Settings &gt; Trash.</p>
                    <p class="mt-2 text-sm font-semibold text-gray-900" x-text="expenseToDelete?.description"></p>
                    <p x-show="actionError" x-text="actionError" class="mt-2 text-sm text-red-600"></p>
                </div>
                <div class="flex gap-2 px-6 py-4 bg-gray-50 border-t border-gray-200">
                    <button type="button" @click="showDeleteModal = false" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white">Cancel</button>
                    <button type="button" @click="confirmDeleteExpense()" :disabled="actionSaving" class="flex-1 py-2.5 rounded-xl bg-red-600 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60" x-text="actionSaving ? 'Moving…' : 'Move to Trash'"></button>
                </div>
            </div>
        </div>
    </template>
    @endif

    {{-- Add Expense Modal --}}
    @if ($canAddExpense)
    <template x-teleport="body">
        <div x-show="showAddModal"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             @click.away="showAddModal = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showAddModal = false"></div>
            <div class="relative z-10 flex max-h-[90dvh] w-full max-w-md flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
                <div class="flex flex-shrink-0 items-center justify-between border-b border-gray-200 px-4 py-4 sm:px-6">
                    <h3 class="text-lg font-bold text-gray-900">Add Expense</h3>
                    <button @click="showAddModal = false" class="p-1 text-gray-400 hover:text-gray-600">@heroicon('o-x-mark', 'w-5 h-5')</button>
                </div>
                <div class="flex-1 space-y-4 overflow-y-auto px-4 py-5 sm:px-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
                        <input type="text" x-model="newExpenseDesc" placeholder="e.g. Ice delivery, Electricity bill..."
                               class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none placeholder:text-gray-400">
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Category</label>
                            <select x-model="newExpenseCategory" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none">
                                @foreach ($expenseCategories as $ec)
                                    <option value="{{ $ec['id'] }}">{{ $ec['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Amount (<span x-text="BmsMoney.symbol"></span>)</label>
                            <input type="number" x-model="newExpenseAmount" placeholder="0" min="0"
                                   class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Payment Method</label>
                            <select x-model="newExpenseMethod" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none">
                                @foreach ($expensePaymentModes as $pm)
                                    <option value="{{ $pm }}">{{ $pm }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Date</label>
                            <input type="date" x-model="newExpenseDate" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Deduct From</label>
                        <select x-model="newExpenseCounter" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none">
                            <option value="">All Counters</option>
                            <template x-for="c in counters" :key="c.id">
                                <option :value="c.id" x-text="c.name"></option>
                            </template>
                        </select>
                        <p class="text-[11px] text-gray-400 mt-1">Pick which counter's profit absorbs this expense, or All Counters for venue-wide costs such as rent.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button @click="recurringEnabled = !recurringEnabled"
                                :class="recurringEnabled ? 'bg-blue-600' : 'bg-gray-300'"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200 focus:outline-none">
                            <span :class="recurringEnabled ? 'translate-x-5' : 'translate-x-0.5'"
                                  class="inline-block h-5 w-5 transform rounded-full bg-white shadow-sm transition-transform duration-200"></span>
                        </button>
                        <span class="text-sm font-medium text-gray-700">Recurring expense</span>
                    </div>
                    <div x-show="recurringEnabled">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Repeat Every</label>
                        <select x-model="newExpenseRepeat" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none">
                            <option>Everyday</option>
                            <option>Weekly</option>
                            <option>Monthly</option>
                            <option>Quarterly</option>
                            <option>Yearly</option>
                        </select>
                        <p class="mt-1 text-[11px] text-gray-400">The repeat schedule is saved with this expense; future entries are not posted automatically.</p>
                    </div>
                    <div x-show="actionError" x-text="actionError" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></div>
                </div>
                <div class="flex flex-shrink-0 gap-2 border-t border-gray-200 bg-gray-50 px-4 py-4 sm:px-6">
                    <button @click="showAddModal = false" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white">Cancel</button>
                    <button @click="addExpense()" :disabled="actionSaving" class="flex-1 py-2.5 rounded-xl bg-blue-600 text-sm font-semibold text-white hover:bg-blue-700 shadow-sm shadow-blue-200 disabled:opacity-60" x-text="actionSaving ? 'Saving…' : 'Add Expense'"></button>
                </div>
            </div>
        </div>
    </template>
    @endif

</div>
@endsection
