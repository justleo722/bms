@extends('layouts.master', ['activeNav' => $activeNav ?? 'customers'])

@section('title', 'Customers')

@push('head')
<script>
window.customersData = function (initialQuery) {
    return {
        searchQuery: initialQuery || '',
        selectedCustomer: null,
        detailTab: 'profile',
        sortBy: 'name',

        customers: window.BmsMock.customers.customers,

        visitHistory: window.BmsMock.customers.visitHistory,

        get filteredCustomers() {
            var q = this.searchQuery.toLowerCase().trim();
            var s = this.sortBy;
            var list = this.customers.filter(function (c) {
                if (!q) return true;
                var haystack = [c.name, c.email, c.phone, c.tier, c.favItem]
                    .map(function (v) { return String(v || '').toLowerCase(); })
                    .join(' ');
                return haystack.includes(q);
            });
            return list.slice().sort(function (a, b) {
                if (s === 'name') return a.name.localeCompare(b.name);
                if (s === 'spent') return b.totalSpent - a.totalSpent;
                if (s === 'visits') return b.visits - a.visits;
                if (s === 'points') return b.loyaltyPts - a.loyaltyPts;
                return 0;
            });
        },

        get selectedVisits() {
            if (!this.selectedCustomer) return [];
            return this.visitHistory.filter(function (v) { return v.customerId === this.selectedCustomer.id; }.bind(this));
        },

        get tierBadgeClass() {
            return function (tier) {
                var m = { gold: 'bg-amber-100 text-amber-700', silver: 'bg-gray-100 text-gray-600', bronze: 'bg-orange-100 text-orange-700', new: 'bg-blue-100 text-blue-700' };
                return m[tier] || 'bg-gray-100 text-gray-600';
            };
        },

        selectCustomer(c) {
            this.selectedCustomer = c;
            this.detailTab = 'profile';
        },

        formatMoney(v) { return (window.BmsMoney ? window.BmsMoney.symbol : '$') + ' ' + (v || 0).toFixed(2); },

        formatDate(d) {
            var parts = d.split('-');
            var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            return months[parseInt(parts[1])-1] + ' ' + parseInt(parts[2]) + ', ' + parts[0];
        },
    };
};
</script>
<style>
.crm-scroll::-webkit-scrollbar { width: 4px; height: 4px; }
.crm-scroll::-webkit-scrollbar-track { background: transparent; }
.crm-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
</style>
@endpush

@section('content')
<div x-data="customersData(@js(is_string(request('q')) ? request('q') : ''))" class="pb-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Customers</h1>
            <p class="mt-1 text-sm text-gray-500">Manage relationships, track visits, and reward loyalty</p>
        </div>
        <div class="flex items-center gap-2 mt-4 sm:mt-0">
            <button class="inline-flex items-center gap-1.5 h-10 px-4 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors">
                @heroicon('o-printer', 'w-4 h-4')
                Export
            </button>
            <button class="inline-flex items-center gap-1.5 h-10 px-4 text-sm font-medium text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition-colors shadow-sm">
                @heroicon('o-plus', 'w-4 h-4')
                Add Customer
            </button>
        </div>
    </div>

    {{-- Search Bar --}}
    <div class="flex items-center gap-3 mb-6">
        <div class="relative flex-1 max-w-md">
            @heroicon('o-magnifying-glass', 'absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400')
            <input type="text" x-model.debounce.300ms="searchQuery" placeholder="Search by name, email or phone..." class="w-full h-10 pl-10 pr-4 text-sm bg-white border border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all placeholder:text-gray-400">
        </div>
        <select x-model="sortBy" class="h-10 px-3 text-sm bg-white border border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none">
            <option value="name">Sort by Name</option>
            <option value="spent">Sort by Spending</option>
            <option value="visits">Sort by Visits</option>
            <option value="points">Sort by Points</option>
        </select>
        <span class="text-xs text-gray-400" x-text="filteredCustomers.length + ' customers'"></span>
    </div>

    {{-- Main Grid --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- Customer List --}}
        <div class="xl:col-span-1 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden max-h-[720px] crm-scroll overflow-y-auto">
            <template x-for="c in filteredCustomers" :key="c.id">
                <button @click="selectCustomer(c)" class="flex items-center gap-3 w-full px-4 py-3.5 text-left transition-all duration-150"
                        :class="{ 'bg-blue-50 border-l-2 border-blue-600': selectedCustomer && selectedCustomer.id === c.id, 'hover:bg-gray-50 border-l-2 border-transparent': !(selectedCustomer && selectedCustomer.id === c.id) }">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0" :class="c.color" x-text="c.initials"></div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-semibold text-gray-900 truncate" x-text="c.name"></p>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-semibold uppercase tracking-wide" :class="tierBadgeClass(c.tier)" x-text="c.tier"></span>
                        </div>
                        <p class="text-xs text-gray-400 truncate" x-text="c.email"></p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-xs font-bold text-gray-900" x-text="formatMoney(c.totalSpent)"></p>
                        <p class="text-[10px] text-gray-400"><span x-text="c.visits"></span> visits</p>
                    </div>
                </button>
            </template>
            <template x-if="filteredCustomers.length === 0">
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    @heroicon('o-users', 'w-8 h-8 text-gray-300')
                    <p class="text-sm font-medium text-gray-500 mt-2">No customers found</p>
                </div>
            </template>
        </div>

        {{-- Detail Panel --}}
        <div class="xl:col-span-2 space-y-6">
            <template x-if="!selectedCustomer">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm flex flex-col items-center justify-center py-16 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mb-4">
                        @heroicon('o-users', 'w-8 h-8 text-gray-300')
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">Select a Customer</h3>
                    <p class="text-sm text-gray-500 mt-1">Choose a customer from the list to view their details</p>
                </div>
            </template>

            <template x-if="selectedCustomer">
                <div>
                    {{-- Profile Header --}}
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="h-24 bg-gradient-to-r from-blue-500 via-purple-500 to-pink-500"></div>
                        <div class="px-6 pb-6">
                            <div class="flex items-end -mt-10 mb-4">
                                <div class="w-20 h-20 rounded-2xl border-4 border-white flex items-center justify-center text-white text-2xl font-bold shadow-lg flex-shrink-0" :class="selectedCustomer.color" x-text="selectedCustomer.initials"></div>
                                <div class="ml-4 pb-1">
                                    <h2 class="text-xl font-bold text-gray-900" x-text="selectedCustomer.name"></h2>
                                    <div class="flex items-center gap-3 mt-0.5">
                                        <span class="text-sm text-gray-500" x-text="selectedCustomer.email"></span>
                                        <span class="text-sm text-gray-400">|</span>
                                        <span class="text-sm text-gray-500" x-text="selectedCustomer.phone"></span>
                                    </div>
                                </div>
                                <div class="ml-auto flex items-center gap-2">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide" :class="tierBadgeClass(selectedCustomer.tier)" x-text="selectedCustomer.tier + ' member'"></span>
                                    <span class="text-xs text-gray-400">Since <span x-text="selectedCustomer.memberSince"></span></span>
                                </div>
                            </div>

                            {{-- Stats Row --}}
                            <div class="grid grid-cols-4 gap-4">
                                <div class="bg-gray-50 rounded-xl p-4 text-center">
                                    <p class="text-2xl font-bold text-gray-900" x-text="selectedCustomer.visits"></p>
                                    <p class="text-xs text-gray-500 mt-0.5">Total Visits</p>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-4 text-center">
                                    <p class="text-2xl font-bold text-gray-900" x-text="formatMoney(selectedCustomer.totalSpent)"></p>
                                    <p class="text-xs text-gray-500 mt-0.5">Total Spending</p>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-4 text-center">
                                    <p class="text-2xl font-bold text-amber-600" x-text="selectedCustomer.loyaltyPts"></p>
                                    <p class="text-xs text-gray-500 mt-0.5">Loyalty Points</p>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-4 text-center">
                                    <p class="text-sm font-semibold text-gray-900" x-text="selectedCustomer.favItem"></p>
                                    <p class="text-xs text-gray-500 mt-0.5">Favourite Item</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Detail Tabs --}}
                    <div class="mt-6 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="flex items-center gap-1 border-b border-gray-100 px-6">
                            <button @click="detailTab = 'profile'" :class="detailTab === 'profile' ? 'border-b-2 border-blue-600 text-blue-600 font-semibold' : 'text-gray-500 hover:text-gray-700'" class="px-3 py-3.5 text-sm font-medium transition-all duration-150 -mb-px">
                                Profile
                            </button>
                            <button @click="detailTab = 'history'" :class="detailTab === 'history' ? 'border-b-2 border-blue-600 text-blue-600 font-semibold' : 'text-gray-500 hover:text-gray-700'" class="px-3 py-3.5 text-sm font-medium transition-all duration-150 -mb-px">
                                Visit History
                            </button>
                            <button @click="detailTab = 'loyalty'" :class="detailTab === 'loyalty' ? 'border-b-2 border-blue-600 text-blue-600 font-semibold' : 'text-gray-500 hover:text-gray-700'" class="px-3 py-3.5 text-sm font-medium transition-all duration-150 -mb-px">
                                Loyalty & Rewards
                            </button>
                        </div>

                        {{-- Profile Tab --}}
                        <div x-show="detailTab === 'profile'" class="p-6 space-y-4">
                            <div class="grid grid-cols-2 gap-6">
                                <div>
                                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</label>
                                    <p class="text-sm text-gray-900 mt-1" x-text="selectedCustomer.email"></p>
                                    <p class="text-sm text-gray-900" x-text="selectedCustomer.phone"></p>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Membership</label>
                                    <p class="text-sm text-gray-900 mt-1" x-text="'Since ' + formatDate(selectedCustomer.memberSince)"></p>
                                    <p class="text-sm text-gray-900"><span x-text="selectedCustomer.tier.charAt(0).toUpperCase() + selectedCustomer.tier.slice(1)"></span> Tier</p>
                                </div>
                            </div>
                            <div>
                                <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</label>
                                <p class="text-sm text-gray-700 mt-1" x-text="selectedCustomer.notes || 'No notes'"></p>
                            </div>
                            <div>
                                <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Favourite Items</label>
                                <p class="text-sm text-gray-700 mt-1" x-text="selectedCustomer.favItem"></p>
                            </div>
                        </div>

                        {{-- Visit History Tab --}}
                        <div x-show="detailTab === 'history'" class="overflow-x-auto crm-scroll">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-gray-50/50">
                                        <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Table</th>
                                        <th class="text-center px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Guests</th>
                                        <th class="text-center px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Items</th>
                                        <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Total</th>
                                        <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Server</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <template x-for="v in selectedVisits" :key="v.id">
                                        <tr class="hover:bg-gray-50/40 transition-colors">
                                            <td class="px-6 py-3">
                                                <span class="text-sm text-gray-900" x-text="formatDate(v.date)"></span>
                                                <span class="text-xs text-gray-400 ml-2" x-text="v.time"></span>
                                            </td>
                                            <td class="px-6 py-3">
                                                <span class="text-sm text-gray-700" x-text="v.table"></span>
                                            </td>
                                            <td class="px-6 py-3 text-center">
                                                <span class="text-sm text-gray-700" x-text="v.guests"></span>
                                            </td>
                                            <td class="px-6 py-3 text-center">
                                                <span class="text-sm text-gray-700" x-text="v.items"></span>
                                            </td>
                                            <td class="px-6 py-3 text-right">
                                                <span class="text-sm font-semibold text-gray-900" x-text="formatMoney(v.total)"></span>
                                            </td>
                                            <td class="px-6 py-3">
                                                <span class="text-sm text-gray-500" x-text="v.server"></span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                            <template x-if="selectedVisits.length === 0">
                                <div class="flex flex-col items-center justify-center py-10 text-center">
                                    <p class="text-sm font-medium text-gray-500">No visit history</p>
                                </div>
                            </template>
                        </div>

                        {{-- Loyalty Tab --}}
                        <div x-show="detailTab === 'loyalty'" class="p-6">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-6">
                                <div class="bg-amber-50 rounded-xl p-5 text-center border border-amber-100">
                                    <p class="text-3xl font-bold text-amber-600" x-text="selectedCustomer.loyaltyPts"></p>
                                    <p class="text-xs font-medium text-amber-800 mt-1">Available Points</p>
                                </div>
                                <div class="bg-blue-50 rounded-xl p-5 text-center border border-blue-100">
                                    <p class="text-3xl font-bold text-blue-600" x-text="selectedCustomer.visits"></p>
                                    <p class="text-xs font-medium text-blue-800 mt-1">Total Visits</p>
                                </div>
                                <div class="bg-emerald-50 rounded-xl p-5 text-center border border-emerald-100">
                                    <p class="text-3xl font-bold text-emerald-600" x-text="'$' + (selectedCustomer.totalSpent / (selectedCustomer.visits || 1)).toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ',')"></p>
                                    <p class="text-xs font-medium text-emerald-800 mt-1">Avg. per Visit</p>
                                </div>
                            </div>
                            <div class="bg-white border border-gray-200 rounded-xl p-5">
                                <h4 class="text-sm font-semibold text-gray-900 mb-3">Tier Benefits</h4>
                                <div class="space-y-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center flex-shrink-0">
                                            <span class="text-xs font-bold text-amber-700">G</span>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">Gold — 10% bonus points</p>
                                            <p class="text-xs text-gray-500">Earn 1.1 points per $1 spent. Priority reservations.</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                                            <span class="text-xs font-bold text-gray-600">S</span>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">Silver — 5% bonus points</p>
                                            <p class="text-xs text-gray-500">Earn 1.05 points per $1 spent. Birthday treat.</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center flex-shrink-0">
                                            <span class="text-xs font-bold text-orange-700">B</span>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">Bronze — Standard earning</p>
                                            <p class="text-xs text-gray-500">Earn 1 point per $1 spent.</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0">
                                            <span class="text-xs font-bold text-blue-700">N</span>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">New — Welcome</p>
                                            <p class="text-xs text-gray-500">Earn 1 point per $1 spent. 100 bonus points on 3rd visit.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
@endsection
