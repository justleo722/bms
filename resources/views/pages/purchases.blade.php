@extends('layouts.master', ['activeNav' => $activeNav ?? 'purchases'])

@section('title', 'Purchases')

@push('head')
<script>
window.purchasesData = function () {
    return {
        activeTab: 'orders',
        showNewPO: false,
        showGRN: false,
        newPOSupplier: '',
        newPOItems: [{ name: '', qty: 0, price: 0 }],
        grnOrder: null,

        suppliers: [
            { id: 1, name: 'Coca-Cola Co.', contact: 'Sarah Mitchell', email: 'sarah@cocacola.com', phone: '(555) 111-0001', orders: 24, total: 12840, status: 'active', since: '2023' },
            { id: 2, name: 'Local Brewery Co.', contact: 'James Walker', email: 'james@localbrew.com', phone: '(555) 111-0002', orders: 18, total: 22360, status: 'active', since: '2022' },
            { id: 3, name: 'Premium Meats Co.', contact: 'Robert Chen', email: 'robert@premiummeats.com', phone: '(555) 111-0003', orders: 31, total: 48750, status: 'active', since: '2021' },
            { id: 4, name: 'FreshPress', contact: 'Emily Green', email: 'emily@freshpress.com', phone: '(555) 111-0004', orders: 12, total: 6420, status: 'onboarding', since: '2024' },
            { id: 5, name: 'Napa Valley Imports', contact: 'Michael Torres', email: 'michael@napavalley.com', phone: '(555) 111-0005', orders: 9, total: 15380, status: 'active', since: '2023' },
        ],

        orders: [
            { id: 'PO-2024-001', supplier: 'Local Brewery Co.', items: 4, total: 1280.00, status: 'pending', date: '2024-06-24', eta: '2024-06-27', staff: 'John D.' },
            { id: 'PO-2024-002', supplier: 'Premium Meats Co.', items: 6, total: 3420.00, status: 'approved', date: '2024-06-23', eta: '2024-06-26', staff: 'Sarah M.' },
            { id: 'PO-2024-003', supplier: 'Coca-Cola Co.', items: 3, total: 840.00, status: 'received', date: '2024-06-22', eta: '2024-06-24', staff: 'John D.' },
            { id: 'PO-2024-004', supplier: 'FreshPress', items: 2, total: 420.00, status: 'cancelled', date: '2024-06-21', eta: '2024-06-25', staff: 'Emma W.' },
            { id: 'PO-2024-005', supplier: 'Napa Valley Imports', items: 5, total: 2850.00, status: 'pending', date: '2024-06-20', eta: '2024-06-28', staff: 'Mike R.' },
            { id: 'PO-2024-006', supplier: 'Premium Meats Co.', items: 8, total: 5600.00, status: 'received', date: '2024-06-19', eta: '2024-06-22', staff: 'Sarah M.' },
            { id: 'PO-2024-007', supplier: 'Local Brewery Co.', items: 3, total: 1860.00, status: 'approved', date: '2024-06-18', eta: '2024-06-25', staff: 'John D.' },
        ],

        goodsReceived: [
            { id: 'GR-001', po: 'PO-2024-003', supplier: 'Coca-Cola Co.', items: 'Coke, Sprite, Mineral Water', qty: 120, value: 840.00, date: '2024-06-24 10:30', receivedBy: 'John D.' },
            { id: 'GR-002', po: 'PO-2024-006', supplier: 'Premium Meats Co.', items: 'Wagyu Patty, Ribeye, Chicken Br.', qty: 85, value: 5600.00, date: '2024-06-22 14:15', receivedBy: 'Sarah M.' },
            { id: 'GR-003', po: 'PO-2024-001', supplier: 'Local Brewery Co.', items: 'IPA, Stout, Lager, Wheat', qty: 48, value: 1280.00, date: '2024-06-21 11:00', receivedBy: 'Mike R.' },
            { id: 'GR-004', po: 'PO-2024-002', supplier: 'Coca-Cola Co.', items: 'Coke, Tonic, Ginger Ale', qty: 90, value: 630.00, date: '2024-06-19 09:45', receivedBy: 'John D.' },
            { id: 'GR-005', po: 'PO-2024-005', supplier: 'FreshPress', items: 'OJ, Lemonade', qty: 30, value: 420.00, date: '2024-06-18 16:20', receivedBy: 'Emma W.' },
        ],

        statusClass(status) {
            var m = { pending: 'bg-amber-50 text-amber-700 ring-amber-600/20', approved: 'bg-blue-50 text-blue-700 ring-blue-600/20', received: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', cancelled: 'bg-red-50 text-red-700 ring-red-600/20', onboarding: 'bg-purple-50 text-purple-700 ring-purple-600/20' };
            return m[status] || 'bg-gray-50 text-gray-700 ring-gray-600/20';
        },

        formatMoney(v) { return (window.BmsMoney ? window.BmsMoney.symbol : '$') + ' ' + (v || 0).toFixed(2); },

        addPOItem() {
            this.newPOItems.push({ name: '', qty: 0, price: 0 });
        },

        removePOItem(index) {
            if (this.newPOItems.length > 1) this.newPOItems.splice(index, 1);
        },

        get new POTotal() {
            return this.newPOItems.reduce(function (sum, item) { return sum + (item.qty * item.price); }, 0);
        },

        openGRN(order) {
            this.grnOrder = order;
            this.showGRN = true;
        },
    };
};
</script>
<style>
.acct-scroll::-webkit-scrollbar { width: 4px; height: 4px; }
.acct-scroll::-webkit-scrollbar-track { background: transparent; }
.acct-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
</style>
@endpush

@section('content')
<div x-data="purchasesData()" class="pb-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Purchases</h1>
            <p class="mt-1 text-sm text-gray-500">Manage suppliers, purchase orders, and goods receiving</p>
        </div>
        <div class="mt-4 grid grid-cols-2 gap-2 sm:mt-0 sm:flex sm:items-center">
            <button class="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl border border-gray-200 bg-white px-4 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                @heroicon('o-printer', 'w-4 h-4')
                Export
            </button>
            <button @click="showNewPO = true" class="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 text-sm font-medium text-white shadow-sm transition-colors hover:bg-blue-700">
                @heroicon('o-plus', 'w-4 h-4')
                New PO
            </button>
        </div>
    </div>

    {{-- Supplier Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 mb-6">
        <template x-for="s in suppliers" :key="s.id">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 hover:shadow-md hover:border-gray-300 transition-all duration-200">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-50 to-blue-100 flex items-center justify-center text-base font-bold text-blue-600 flex-shrink-0">
                        <span x-text="s.name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase()"></span>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium capitalize ring-1 ring-inset" :class="statusClass(s.status)" x-text="s.status"></span>
                </div>
                <h3 class="text-sm font-semibold text-gray-900 truncate" x-text="s.name"></h3>
                <p class="text-xs text-gray-500 mt-0.5 truncate" x-text="s.contact"></p>
                <div class="mt-3 pt-3 border-t border-gray-100 grid grid-cols-2 gap-2 text-center">
                    <div>
                        <p class="text-xs text-gray-400">Orders</p>
                        <p class="text-sm font-bold text-gray-900" x-text="s.orders"></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Total</p>
                        <p class="text-sm font-bold text-gray-900" x-text="formatMoney(s.total)"></p>
                    </div>
                </div>
                <div class="mt-2 flex items-center gap-1 justify-center text-[10px] text-gray-400">
                    <span>Since</span>
                    <span x-text="s.since"></span>
                </div>
            </div>
        </template>
    </div>

    {{-- Tabs: Purchase Orders | Goods Received --}}
    <div class="mb-6">
        <div class="flex items-center gap-1 border-b border-gray-200">
            <button @click="activeTab = 'orders'" :class="activeTab === 'orders' ? 'border-b-2 border-blue-600 text-blue-600 font-semibold' : 'text-gray-500 hover:text-gray-700'" class="px-4 py-3 text-sm font-medium transition-all duration-150 -mb-px">
                Purchase Orders
            </button>
            <button @click="activeTab = 'received'" :class="activeTab === 'received' ? 'border-b-2 border-blue-600 text-blue-600 font-semibold' : 'text-gray-500 hover:text-gray-700'" class="px-4 py-3 text-sm font-medium transition-all duration-150 -mb-px">
                Goods Received
            </button>
        </div>
    </div>

    {{-- Purchase Orders Table --}}
    <div x-show="activeTab === 'orders'" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="scroll-x overflow-x-auto acct-scroll">
            <table class="w-full min-w-[900px] text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/50">
                        <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">PO Number</th>
                        <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Supplier</th>
                        <th class="text-center px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Items</th>
                        <th class="text-right px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Total</th>
                        <th class="text-center px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="text-center px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">ETA</th>
                        <th class="text-right px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <template x-for="o in orders" :key="o.id">
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-5 py-3.5">
                                <span class="text-sm font-semibold text-gray-900" x-text="o.id"></span>
                                <p class="text-xs text-gray-400" x-text="o.staff"></p>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="text-sm text-gray-700" x-text="o.supplier"></span>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="text-sm font-medium text-gray-700" x-text="o.items"></span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <span class="text-sm font-semibold text-gray-900" x-text="formatMoney(o.total)"></span>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-medium capitalize ring-1 ring-inset" :class="statusClass(o.status)" x-text="o.status"></span>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="text-sm text-gray-600" x-text="o.eta"></span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <span class="text-sm text-gray-500" x-text="o.date"></span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <button x-show="o.status === 'approved'" @click="openGRN(o)" class="text-xs font-medium text-emerald-600 hover:text-emerald-700 transition-colors">Receive</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <template x-if="orders.length === 0">
            <div class="flex flex-col items-center justify-center py-12 text-center">
                <span class="text-3xl mb-2">📋</span>
                <p class="text-sm font-medium text-gray-500">No purchase orders yet</p>
            </div>
        </template>
    </div>

    {{-- Goods Received Table --}}
    <div x-show="activeTab === 'received'" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="scroll-x overflow-x-auto acct-scroll">
            <table class="w-full min-w-[1000px] text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/50">
                        <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">GR Number</th>
                        <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">PO Reference</th>
                        <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Supplier</th>
                        <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Items</th>
                        <th class="text-center px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Qty</th>
                        <th class="text-right px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Value</th>
                        <th class="text-center px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Received By</th>
                        <th class="text-right px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <template x-for="gr in goodsReceived" :key="gr.id">
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-5 py-3.5">
                                <span class="text-sm font-semibold text-gray-900" x-text="gr.id"></span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="text-sm text-blue-600 font-medium" x-text="gr.po"></span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="text-sm text-gray-700" x-text="gr.supplier"></span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="text-sm text-gray-600 truncate block max-w-[160px]" x-text="gr.items"></span>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="text-sm font-semibold text-gray-900" x-text="gr.qty"></span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <span class="text-sm font-semibold text-gray-900" x-text="formatMoney(gr.value)"></span>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="text-sm text-gray-600" x-text="gr.receivedBy"></span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <span class="text-sm text-gray-500" x-text="gr.date"></span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <template x-if="goodsReceived.length === 0">
            <div class="flex flex-col items-center justify-center py-12 text-center">
                <span class="text-3xl mb-2">📦</span>
                <p class="text-sm font-medium text-gray-500">No goods received yet</p>
            </div>
        </template>
    </div>

    {{-- New PO Modal --}}
    <template x-teleport="body">
        <div x-show="showNewPO"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             @click.away="showNewPO = false">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showNewPO = false"></div>
            <div class="relative z-10 max-h-[90dvh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl sm:p-6">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-bold text-gray-900">New Purchase Order</h3>
                    <button @click="showNewPO = false" class="p-1 text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Supplier</label>
                        <select x-model="newPOSupplier" class="w-full h-11 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                            <option value="">Select supplier...</option>
                            @php $suppliersList = ['Coca-Cola Co.', 'Local Brewery Co.', 'Premium Meats Co.', 'FreshPress', 'Napa Valley Imports']; @endphp
                            @foreach ($suppliersList as $s)
                            <option value="{{ $s }}">{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-sm font-medium text-gray-700">Items</label>
                            <button @click="addPOItem()" class="text-xs font-medium text-blue-600 hover:text-blue-700 transition-colors">+ Add Item</button>
                        </div>
                        <div class="space-y-2">
                            <template x-for="(item, idx) in newPOItems" :key="idx">
                                <div class="flex items-center gap-2">
                                    <input type="text" x-model="item.name" placeholder="Item name" class="h-10 min-w-0 flex-1 rounded-xl border border-gray-300 bg-gray-50 px-3 text-sm outline-none transition-all focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                                    <input type="number" x-model="item.qty" min="0" placeholder="Qty" class="h-10 w-16 rounded-xl border border-gray-300 bg-gray-50 px-2 text-center text-sm outline-none transition-all focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 sm:w-20 sm:px-3">
                                    <input type="number" x-model="item.price" min="0" step="0.01" placeholder="Price" class="h-10 w-20 rounded-xl border border-gray-300 bg-gray-50 px-2 text-right text-sm outline-none transition-all focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 sm:w-24 sm:px-3">
                                    <button x-show="newPOItems.length > 1" @click="removePOItem(idx)" class="p-2 text-gray-400 hover:text-red-500 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="bg-gray-50 rounded-xl p-4 flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700">Total</span>
                        <span class="text-xl font-bold text-gray-900">$<span x-text="new POTotal.toFixed(2)"></span></span>
                    </div>
                </div>

                <div class="flex gap-2 mt-5">
                    <button @click="showNewPO = false" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">Cancel</button>
                    <button @click="showNewPO = false" class="flex-1 py-2.5 rounded-xl bg-blue-600 text-sm font-semibold text-white hover:bg-blue-700 transition-colors shadow-sm shadow-blue-200">Create PO</button>
                </div>
            </div>
        </div>
    </template>

    {{-- GRN Confirmation Modal --}}
    <template x-teleport="body">
        <div x-show="showGRN"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             @click.away="showGRN = false">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showGRN = false"></div>
            <div class="relative z-10 max-h-[90dvh] w-full max-w-md overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl sm:p-6">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-bold text-gray-900">Receive Goods</h3>
                    <button @click="showGRN = false" class="p-1 text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <template x-if="grnOrder">
                    <div class="space-y-4">
                        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span class="text-sm font-semibold text-emerald-700">Confirm goods received</span>
                            </div>
                            <p class="text-xs text-emerald-600">This will update inventory stock levels automatically.</p>
                        </div>

                        <div class="bg-gray-50 rounded-xl p-4 space-y-2">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-500">PO Number</span>
                                <span class="font-semibold text-gray-900" x-text="grnOrder.id"></span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-500">Supplier</span>
                                <span class="font-medium text-gray-900" x-text="grnOrder.supplier"></span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-500">Items</span>
                                <span class="font-medium text-gray-900" x-text="grnOrder.items + ' items'"></span>
                            </div>
                            <div class="border-t border-gray-200 pt-2 mt-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-bold text-gray-900">Total Value</span>
                                    <span class="text-lg font-bold text-gray-900" x-text="formatMoney(grnOrder.total)"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <div class="flex gap-2 mt-5">
                    <button @click="showGRN = false" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">Cancel</button>
                    <button @click="showGRN = false" class="flex-1 py-2.5 rounded-xl bg-emerald-600 text-sm font-semibold text-white hover:bg-emerald-700 transition-colors shadow-sm shadow-emerald-200">Confirm Receipt</button>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection
