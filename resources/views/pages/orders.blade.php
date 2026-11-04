@extends('layouts.master', ['activeNav' => $activeNav ?? 'orders'])

@section('title', 'Orders')

@push('head')
<script>
window.ordersData = function (initialQuery) {
    return {
        activeTab: 'active',
        searchQuery: initialQuery || '',
        statusFilter: '',
        paymentFilter: '',
        dateFilter: '',

        showFormSuccess: false,
        resForm: { name: '', phone: '', guests: 2, date: '', time: '', notes: '' },

        showDeleteModal: false,
        reservationToDelete: null,

        orders: window.BmsMock.orders.orders,

        reservations: window.BmsTrash.readCollection('bms.reservations', window.BmsMock.orders.reservations),

        get activeOrders() {
            return this.orders.filter(function (o) { return o.status === 'pending' || o.status === 'confirmed' || o.status === 'preparing'; });
        },
        get completedOrders() {
            return this.orders.filter(function (o) { return o.status === 'completed'; });
        },
        get cancelledOrders() {
            return this.orders.filter(function (o) { return o.status === 'cancelled'; });
        },

        get filteredActive() {
            var q = this.searchQuery.toLowerCase().trim();
            var s = this.statusFilter;
            var p = this.paymentFilter;
            var d = this.dateFilter;
            var self = this;
            return this.activeOrders.filter(function (o) {
                if (q && !self.orderMatches(o, q)) return false;
                if (s && o.status !== s) return false;
                if (p && o.payment !== p) return false;
                if (d) {
                    var today = new Date().toISOString().slice(0, 10);
                    if (d === 'today' && o.date.slice(0, 10) !== today) return false;
                    if (d === 'week') { var weekAgo = new Date(Date.now() - 7 * 86400000).toISOString().slice(0, 10); if (o.date.slice(0, 10) < weekAgo) return false; }
                }
                return true;
            });
        },

        get filteredCompleted() {
            var q = this.searchQuery.toLowerCase().trim();
            var self = this;
            return this.completedOrders.filter(function (o) {
                return !q || self.orderMatches(o, q);
            });
        },

        get filteredCancelled() {
            var q = this.searchQuery.toLowerCase().trim();
            var self = this;
            return this.cancelledOrders.filter(function (o) {
                return !q || self.orderMatches(o, q);
            });
        },

        // An order matches on its reference, customer, table, phone or status.
        orderMatches(o, q) {
            var haystack = [o.id, o.customer, o.table, o.phone, o.status, o.payment]
                .map(function (v) { return String(v || '').toLowerCase(); })
                .join(' ');
            return haystack.includes(q);
        },

        get filteredReservations() {
            var q = this.searchQuery.toLowerCase().trim();
            return this.reservations.filter(function (r) {
                if (!q) { return true; }
                var haystack = [r.customer, r.phone, r.date, r.time, r.status, r.notes]
                    .map(function (v) { return String(v || '').toLowerCase(); })
                    .join(' ');
                return haystack.includes(q);
            });
        },

        statusBadge(status) {
            var map = {
                pending: 'bg-gray-100 text-gray-600 ring-1 ring-gray-300/30',
                confirmed: 'bg-blue-50 text-blue-700 ring-1 ring-blue-600/20',
                preparing: 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20',
                completed: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20',
                cancelled: 'bg-red-50 text-red-700 ring-1 ring-red-600/20',
                refunded: 'bg-red-50 text-red-700 ring-1 ring-red-600/20',
                paid: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20',
                seated: 'bg-purple-50 text-purple-700 ring-1 ring-purple-600/20',
            };
            return map[status] || 'bg-gray-100 text-gray-600 ring-1 ring-gray-300/30';
        },

        statusIcon(status) {
            var map = {
                pending: '⏳', confirmed: '✅', preparing: '👨‍🍳', completed: '🍽️', cancelled: '❌', paid: '💰', refunded: '↩️', seated: '🪑',
            };
            return map[status] || '📋';
        },

        formatMoney(v) { return (window.BmsMoney ? window.BmsMoney.symbol : '$') + ' ' + (v || 0).toFixed(2); },
        formatDate(d) { return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }); },
        formatTime(d) { return new Date(d).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }); },

        submitReservation() {
            var f = this.resForm;
            if (!f.name || !f.phone || !f.date || !f.time) return;
            this.reservations.unshift({
                id: this.reservations.length + 1,
                customer: f.name,
                phone: f.phone,
                guests: f.guests,
                date: f.date,
                time: f.time,
                notes: f.notes,
                status: 'pending',
            });
            this.showFormSuccess = true;
            this.resForm = { name: '', phone: '', guests: 2, date: '', time: '', notes: '' };
            var self = this;
            setTimeout(function () { self.showFormSuccess = false; }, 3000);
        },

        switchTab(tab) {
            this.activeTab = tab;
            this.searchQuery = '';
            this.statusFilter = '';
            this.paymentFilter = '';
            this.dateFilter = '';
        },

        askDeleteReservation(res) {
            this.reservationToDelete = res;
            this.showDeleteModal = true;
        },

        closeDeleteReservation() {
            this.showDeleteModal = false;
            this.reservationToDelete = null;
        },

        confirmDeleteReservation() {
            if (!this.reservationToDelete) return;
            window.BmsTrash.add('Reservation', this.reservationToDelete.name, this.reservationToDelete, 'bms.reservations');
            var id = this.reservationToDelete.id;
            this.reservations = this.reservations.filter(function (r) { return r.id !== id; });
            window.BmsTrash.writeCollection('bms.reservations', this.reservations);
            this.closeDeleteReservation();
        },
    };
};
</script>
@endpush

@section('content')
<div x-data="ordersData(@js(is_string(request('q')) ? request('q') : ''))" class="pb-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Orders</h1>
            <p class="mt-1 text-sm text-gray-500">
                <span x-text="activeOrders.length"></span> active &middot;
                <span x-text="completedOrders.length"></span> completed &middot;
                <span x-text="cancelledOrders.length"></span> cancelled &middot;
                <span x-text="reservations.length"></span> reservations
            </p>
        </div>
        <div class="mt-4 flex items-center gap-2 sm:mt-0">
            <div class="relative w-full sm:w-auto">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" x-model.debounce.300ms="searchQuery" placeholder="Search orders..." class="h-10 w-full pl-10 pr-4 text-sm bg-white border border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all placeholder:text-gray-400 sm:w-56">
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="scroll-x mb-6 flex w-fit max-w-full items-center gap-1 overflow-x-auto rounded-xl bg-gray-100 p-1 no-scrollbar">
        <button @click="switchTab('active')" :class="activeTab === 'active' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'" class="flex-shrink-0 whitespace-nowrap px-3 py-2 rounded-lg text-sm font-medium transition-all duration-150 sm:px-4">
            <span class="flex items-center gap-2">Active <span class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full font-bold" x-text="activeOrders.length"></span></span>
        </button>
        <button @click="switchTab('completed')" :class="activeTab === 'completed' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'" class="flex-shrink-0 whitespace-nowrap px-3 py-2 rounded-lg text-sm font-medium transition-all duration-150 sm:px-4">
            <span class="flex items-center gap-2">Completed <span class="text-xs bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded-full font-bold" x-text="completedOrders.length"></span></span>
        </button>
        <button @click="switchTab('cancelled')" :class="activeTab === 'cancelled' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'" class="flex-shrink-0 whitespace-nowrap px-3 py-2 rounded-lg text-sm font-medium transition-all duration-150 sm:px-4">
            <span class="flex items-center gap-2">Cancelled <span class="text-xs bg-red-100 text-red-700 px-1.5 py-0.5 rounded-full font-bold" x-text="cancelledOrders.length"></span></span>
        </button>
        <button @click="switchTab('reservations')" :class="activeTab === 'reservations' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'" class="flex-shrink-0 whitespace-nowrap px-3 py-2 rounded-lg text-sm font-medium transition-all duration-150 sm:px-4">
            <span class="flex items-center gap-2">Reservations <span class="text-xs bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded-full font-bold" x-text="reservations.length"></span></span>
        </button>
    </div>

    {{-- Active Tab --}}
    <div x-show="activeTab === 'active'" x-cloak>
        {{-- Filters --}}
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:gap-3">
            <select x-model="statusFilter" class="h-10 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 sm:w-auto">
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="confirmed">Confirmed</option>
                <option value="preparing">Preparing</option>
            </select>
            <select x-model="paymentFilter" class="h-10 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 sm:w-auto">
                <option value="">All Payments</option>
                <option value="pending">Pending</option>
                <option value="paid">Paid</option>
            </select>
            <select x-model="dateFilter" class="h-10 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 sm:w-auto">
                <option value="">All Time</option>
                <option value="today">Today</option>
                <option value="week">This Week</option>
            </select>
        </div>

        <div class="scroll-x overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="w-full min-w-[900px] text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/50">
                            <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Order</th>
                            <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Table</th>
                            <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Items</th>
                            <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Payment</th>
                            <th class="text-right px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template x-for="order in filteredActive" :key="order.id">
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-5 py-4">
                                    <span class="font-semibold text-gray-900" x-text="order.id"></span>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center">
                                            <span class="text-xs font-bold text-blue-700" x-text="order.customer.split(' ').map(function(w){return w[0];}).join('').slice(0,2)"></span>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900" x-text="order.customer"></p>
                                            <p class="text-xs text-gray-400" x-text="order.phone"></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="font-medium text-gray-700" x-text="order.table"></span>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="text-gray-600" x-text="order.items + ' items'"></span>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="font-semibold text-gray-900" x-text="formatMoney(order.amount)"></span>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium" :class="statusBadge(order.status)">
                                        <span x-text="statusIcon(order.status)"></span>
                                        <span class="capitalize" x-text="order.status"></span>
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium" :class="statusBadge(order.payment)">
                                        <span class="w-1.5 h-1.5 rounded-full" :class="order.payment === 'paid' ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                                        <span class="capitalize" x-text="order.payment"></span>
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <span class="text-xs text-gray-400" x-text="formatTime(order.date)"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            <template x-if="filteredActive.length === 0">
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <span class="text-3xl mb-2">📭</span>
                    <p class="text-sm font-medium text-gray-500">No active orders match your filters</p>
                </div>
            </template>
        </div>
    </div>

    {{-- Completed Tab --}}
    <div x-show="activeTab === 'completed'" x-cloak>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="scroll-x overflow-x-auto">
                <table class="w-full min-w-[900px] text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/50">
                            <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Order</th>
                            <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Table</th>
                            <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Items</th>
                            <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Payment</th>
                            <th class="text-right px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template x-for="order in filteredCompleted" :key="order.id">
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-5 py-4"><span class="font-semibold text-gray-900" x-text="order.id"></span></td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-emerald-100 to-emerald-200 flex items-center justify-center">
                                            <span class="text-xs font-bold text-emerald-700" x-text="order.customer.split(' ').map(function(w){return w[0];}).join('').slice(0,2)"></span>
                                        </div>
                                        <span class="font-medium text-gray-900" x-text="order.customer"></span>
                                    </div>
                                </td>
                                <td class="px-5 py-4"><span class="font-medium text-gray-700" x-text="order.table"></span></td>
                                <td class="px-5 py-4"><span class="text-gray-600" x-text="order.items + ' items'"></span></td>
                                <td class="px-5 py-4"><span class="font-semibold text-gray-900" x-text="formatMoney(order.amount)"></span></td>
                                <td class="px-5 py-4"><span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium" :class="statusBadge(order.status)"><span x-text="statusIcon(order.status)"></span> <span class="capitalize" x-text="order.status"></span></span></td>
                                <td class="px-5 py-4"><span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium" :class="statusBadge(order.payment)"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> <span class="capitalize" x-text="order.payment"></span></span></td>
                                <td class="px-5 py-4 text-right"><span class="text-xs text-gray-400" x-text="formatDate(order.date)"></span></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <template x-if="filteredCompleted.length === 0">
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <span class="text-3xl mb-2">📭</span>
                    <p class="text-sm font-medium text-gray-500">No completed orders yet</p>
                </div>
            </template>
        </div>
    </div>

    {{-- Cancelled Tab --}}
    <div x-show="activeTab === 'cancelled'" x-cloak>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="scroll-x overflow-x-auto">
                <table class="w-full min-w-[820px] text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/50">
                            <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Order</th>
                            <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Table</th>
                            <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Payment</th>
                            <th class="text-right px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template x-for="order in filteredCancelled" :key="order.id">
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-5 py-4"><span class="font-semibold text-gray-900" x-text="order.id"></span></td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-red-100 to-red-200 flex items-center justify-center">
                                            <span class="text-xs font-bold text-red-700" x-text="order.customer.split(' ').map(function(w){return w[0];}).join('').slice(0,2)"></span>
                                        </div>
                                        <span class="font-medium text-gray-900" x-text="order.customer"></span>
                                    </div>
                                </td>
                                <td class="px-5 py-4"><span class="font-medium text-gray-700" x-text="order.table"></span></td>
                                <td class="px-5 py-4"><span class="font-semibold text-gray-900" x-text="formatMoney(order.amount)"></span></td>
                                <td class="px-5 py-4"><span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium" :class="statusBadge(order.status)"><span x-text="statusIcon(order.status)"></span> <span class="capitalize" x-text="order.status"></span></span></td>
                                <td class="px-5 py-4"><span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium" :class="statusBadge(order.payment)"><span class="w-1.5 h-1.5 rounded-full" :class="order.payment === 'refunded' ? 'bg-red-500' : 'bg-amber-500'"></span> <span class="capitalize" x-text="order.payment"></span></span></td>
                                <td class="px-5 py-4 text-right"><span class="text-xs text-gray-400" x-text="formatDate(order.date)"></span></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <template x-if="filteredCancelled.length === 0">
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <span class="text-3xl mb-2">🎉</span>
                    <p class="text-sm font-medium text-gray-500">No cancelled orders</p>
                </div>
            </template>
        </div>
    </div>

    {{-- Reservations Tab --}}
    <div x-show="activeTab === 'reservations'" x-cloak>
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            {{-- Reservation Form --}}
            <div class="lg:col-span-2">
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
                    <h3 class="text-base font-bold text-gray-900 mb-5">New Reservation</h3>

                    <div x-show="showFormSuccess" x-cloak class="mb-4 px-4 py-3 bg-emerald-50 border border-emerald-200 rounded-xl text-sm font-medium text-emerald-700 flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Reservation saved successfully!
                    </div>

                    <form @submit.prevent="submitReservation" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Customer Name</label>
                            <input type="text" x-model="resForm.name" placeholder="Full name" required class="w-full h-11 px-3 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all placeholder:text-gray-400">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Phone Number</label>
                            <input type="tel" x-model="resForm.phone" placeholder="(555) 000-0000" required class="w-full h-11 px-3 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all placeholder:text-gray-400">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Number of Guests</label>
                            <div class="flex items-center gap-3">
                                <button @click="resForm.guests = Math.max(1, resForm.guests - 1)" type="button" class="w-11 h-11 flex items-center justify-center rounded-xl border border-gray-300 text-gray-600 hover:bg-gray-100 transition-colors text-lg font-medium">−</button>
                                <span class="w-16 text-center text-lg font-bold text-gray-900" x-text="resForm.guests"></span>
                                <button @click="resForm.guests = Math.min(20, resForm.guests + 1)" type="button" class="w-11 h-11 flex items-center justify-center rounded-xl border border-gray-300 text-gray-600 hover:bg-gray-100 transition-colors text-lg font-medium">+</button>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Date</label>
                                <input type="date" x-model="resForm.date" required class="w-full h-11 px-3 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Time</label>
                                <input type="time" x-model="resForm.time" required class="w-full h-11 px-3 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Special Notes</label>
                            <textarea x-model="resForm.notes" rows="3" placeholder="Allergies, preferences, special occasions..." class="w-full px-3 py-2.5 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all resize-none placeholder:text-gray-400"></textarea>
                        </div>
                        <button type="submit" class="w-full h-11 rounded-xl bg-gradient-to-r from-blue-600 to-blue-500 text-sm font-semibold text-white hover:from-blue-700 hover:to-blue-600 transition-all shadow-sm shadow-blue-200">
                            Create Reservation
                        </button>
                    </form>
                </div>
            </div>

            {{-- Reservations List --}}
            <div class="lg:col-span-3">
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="scroll-x overflow-x-auto">
                        <table class="w-full min-w-[760px] text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 bg-gray-50/50">
                                    <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Customer</th>
                                    <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Contact</th>
                                    <th class="text-center px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Guests</th>
                                    <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Time</th>
                                    <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="text-center px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <template x-for="(res, index) in filteredReservations" :key="res.id">
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="px-5 py-4">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-purple-100 to-purple-200 flex items-center justify-center">
                                                    <span class="text-xs font-bold text-purple-700" x-text="res.customer.split(' ').map(function(w){return w[0];}).join('').slice(0,2)"></span>
                                                </div>
                                                <span class="font-medium text-gray-900" x-text="res.customer"></span>
                                            </div>
                                        </td>
                                        <td class="px-5 py-4"><span class="text-gray-600" x-text="res.phone"></span></td>
                                        <td class="px-5 py-4 text-center"><span class="font-semibold text-gray-700" x-text="res.guests"></span></td>
                                        <td class="px-5 py-4"><span class="text-gray-700" x-text="res.date"></span></td>
                                        <td class="px-5 py-4"><span class="text-gray-700 font-medium" x-text="res.time"></span></td>
                                        <td class="px-5 py-4">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium" :class="statusBadge(res.status)">
                                                <span class="w-1.5 h-1.5 rounded-full" :class="{'bg-blue-500': res.status === 'confirmed', 'bg-amber-500': res.status === 'pending', 'bg-emerald-500': res.status === 'seated'}"></span>
                                                <span class="capitalize" x-text="res.status"></span>
                                            </span>
                                        </td>
                                        <td class="px-5 py-4 text-center">
                                            <button @click="askDeleteReservation(res)" class="p-1.5 text-gray-300 hover:text-red-500 transition-colors" title="Delete">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <template x-if="filteredReservations.length === 0">
                        <div class="flex flex-col items-center justify-center py-12 text-center">
                            <span class="text-3xl mb-2">📅</span>
                            <p class="text-sm font-medium text-gray-500">No reservations yet</p>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    {{-- Delete Confirmation Modal --}}
    <template x-teleport="body">
        <div x-show="showDeleteModal"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             @click.away="closeDeleteReservation()"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="closeDeleteReservation()"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm z-10"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
                <div class="px-6 py-5 text-center">
                    <div class="inline-flex items-center justify-center w-14 h-14 bg-red-100 rounded-full mb-4">
                        @heroicon('o-exclamation-triangle', 'w-7 h-7 text-red-600')
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Delete Reservation</h3>
                    <p class="text-sm text-gray-500 mb-1">Are you sure you want to delete</p>
                    <p class="text-base font-bold text-gray-900" x-text="reservationToDelete?.customer"></p>
                    <p class="text-xs text-gray-400 mt-1">This action cannot be undone.</p>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex gap-2">
                    <button @click="closeDeleteReservation()" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white">Cancel</button>
                    <button @click="confirmDeleteReservation()" class="flex-1 py-2.5 rounded-xl bg-red-600 text-sm font-semibold text-white hover:bg-red-700 shadow-sm shadow-red-200">Delete</button>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection
