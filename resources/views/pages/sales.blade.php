@extends('layouts.master', ['activeNav' => $activeNav ?? 'sales'])

@section('title', 'Sales')

@push('head')
@include('partials.receipt-print')
<style>
    /* Hidden on screen, shown only when the browser prints (Export Sales). */
    .print-report { display: none; }
    @media print {
        @page {
            size: A4 portrait;
            margin: 12mm;
        }
        /* Dockets and bills print through a dedicated 80mm frame, so only the
           sales report below needs page-level print rules. */
        .no-print,
        header,
        footer,
        nav.fixed { display: none !important; }
        main { padding: 0 !important; }
        .print-report { display: block !important; }
        .print-avoid-break { page-break-inside: avoid; }

        .print-report table { width: 100%; border-collapse: collapse; font-size: 11px; color: #111111; }
        .print-report th,
        .print-report td { border: 1px solid #000; padding: 5px 7px; text-align: left; }
        .print-report thead { display: table-header-group; }
        .print-report tr { page-break-inside: avoid; }
        .print-report th { background: #eeeeee !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
<style>
    .accordion-divider > * + * {
        border-top: 1px solid rgba(0, 0, 0, 0.05) !important;
    }
</style>
@endpush

@push('scripts')
<script>
window.salesData = function (initialQuery, sales, todayDate, isAdmin, products, tables, paymentModes) {
    return {
        searchQuery: initialQuery || '',
        dateFilter: 'today',
        dateFrom: '',
        dateTo: '',
        timeFrom: '',
        timeTo: '',
        counterFilter: 'all',
        attendantFilter: 'all',
        expandedSale: null,
        selectedSale: null,
        showDocketModal: false,
        showBillModal: false,
        showEditModal: false,
        showBackdateModal: false,
        showDeleteModal: false,
        isAdmin: isAdmin,
        products: products || [],
        tables: tables || [],
        paymentModes: paymentModes || [],
        editingSale: null,
        editItems: [],
        editNotes: '',
        editTableId: '',
        editPaymentModeId: '',
        editPaymentReference: '',
        backdateSale: null,
        backdateDate: '',
        deleteOrderIds: [],
        deleteLabel: '',
        actionSaving: false,
        actionError: '',
        selectedSales: [],
        billSales: [],
        docketPrintStatus: '',
        billPrintStatus: '',
        generatedAt: '',

        sales: Array.isArray(sales) ? sales : [],
        todayDate: todayDate,

        get filteredSales() {
            var q = this.searchQuery.toLowerCase().trim();
            var d = this.dateFilter;
            var c = this.counterFilter;
            var a = this.attendantFilter;
            var self = this;
            return this.sales.filter(function (sale) {
                if (q) {
                    var names = (sale.items || []).map(function (i) { return i.name; }).join(' ');
                    var haystack = [sale.id, sale.table, sale.counter, sale.staff, sale.status, names]
                        .map(function (v) { return String(v || '').toLowerCase(); })
                        .join(' ');
                    if (!haystack.includes(q)) return false;
                }
                if (c !== 'all' && sale.counter !== c) return false;
                if (a !== 'all' && sale.staff !== a) return false;
                if (d === 'today') {
                    if (sale.date.slice(0, 10) !== self.todayDate) return false;
                }
                if (d === 'yesterday') {
                    var yesterdayDate = new Date(self.todayDate + 'T00:00:00');
                    yesterdayDate.setDate(yesterdayDate.getDate() - 1);
                    var yesterday = yesterdayDate.getFullYear() + '-' + String(yesterdayDate.getMonth() + 1).padStart(2, '0') + '-' + String(yesterdayDate.getDate()).padStart(2, '0');
                    if (sale.date.slice(0, 10) !== yesterday) return false;
                }
                if (d === 'week') {
                    var weekStart = new Date(self.todayDate + 'T00:00:00');
                    weekStart.setDate(weekStart.getDate() - ((weekStart.getDay() + 6) % 7));
                    var start = weekStart.getFullYear() + '-' + String(weekStart.getMonth() + 1).padStart(2, '0') + '-' + String(weekStart.getDate()).padStart(2, '0');
                    if (sale.date.slice(0, 10) < start || sale.date.slice(0, 10) > self.todayDate) return false;
                }
                if (d === 'custom') {
                    var saleDateStr = sale.date.slice(0, 10);
                    var saleTimeStr = sale.date.slice(11, 16);
                    if (self.dateFrom && saleDateStr < self.dateFrom) return false;
                    if (self.dateTo && saleDateStr > self.dateTo) return false;
                    if (self.timeFrom && saleDateStr === self.dateFrom && saleTimeStr < self.timeFrom) return false;
                    if (self.timeTo && saleDateStr === self.dateTo && saleTimeStr > self.timeTo) return false;
                }
                return true;
            });
        },

        get todayTotal() {
            return this.sales.filter(function (s) { return s.date.slice(0, 10) === this.todayDate && s.status === 'paid'; }.bind(this))
                .reduce(function (sum, s) { return sum + s.total; }, 0);
        },

        get todayCount() {
            return this.sales.filter(function (s) { return s.date.slice(0, 10) === this.todayDate && s.status === 'paid'; }.bind(this)).length;
        },

        get billTotal() {
            return this.billSales.reduce(function (sum, s) { return sum + s.total; }, 0);
        },

        get filteredTotal() {
            return this.filteredSales.filter(function (sale) { return sale.status === 'paid'; })
                .reduce(function (sum, sale) { return sum + sale.total; }, 0);
        },

        get filteredProfit() {
            return this.filteredSales.filter(function (sale) { return sale.status === 'paid'; })
                .reduce(function (sum, sale) { return sum + sale.total - (sale.cost || 0); }, 0);
        },

        get printFilterSummary() {
            var parts = [];
            if (this.dateFilter === 'today') parts.push('Date: Today');
            else if (this.dateFilter === 'yesterday') parts.push('Date: Yesterday');
            else if (this.dateFilter === 'week') parts.push('Date: This week');
            else if (this.dateFilter === 'custom') parts.push('Date: ' + (this.dateFrom || 'any') + ' to ' + (this.dateTo || 'any'));
            else parts.push('Date: All');
            parts.push('Counter: ' + (this.counterFilter === 'all' ? 'All' : this.counterFilter));
            parts.push('Attendant: ' + (this.attendantFilter === 'all' ? 'All' : this.attendantFilter));
            if (this.searchQuery.trim()) parts.push('Search: "' + this.searchQuery.trim() + '"');
            return parts.join('   |   ');
        },

        viewDocket(sale) {
            this.selectedSale = sale;
            this.showDocketModal = true;
        },

        openEdit(sale) {
            this.editingSale = sale;
            this.editItems = sale.items.map(function (item) {
                return {
                    productKey: item.type + ':' + item.productId,
                    qty: item.qty,
                    name: item.name,
                    type: item.type,
                    productId: item.productId,
                };
            });
            this.editNotes = sale.notes || '';
            this.editTableId = sale.tableId || '';
            var mode = this.paymentModes.find(function (payment) { return payment.name === sale.paymentMethod; });
            this.editPaymentModeId = mode ? mode.id : '';
            this.editPaymentReference = sale.paymentRef || '';
            this.actionError = '';
            this.showEditModal = true;
        },

        addEditItem() {
            var product = this.products[0];
            if (!product) return;
            this.editItems.push({
                productKey: product.type + ':' + product.id,
                qty: 1,
                name: product.name,
                type: product.type,
                productId: product.id,
            });
        },

        updateEditProduct(item) {
            var parts = item.productKey.split(':');
            var product = this.products.find(function (entry) {
                return entry.type === parts[0] && Number(entry.id) === Number(parts[1]);
            });
            if (product) {
                item.type = product.type;
                item.productId = product.id;
                item.name = product.name;
            }
        },

        saveEdit() {
            if (!this.editingSale || this.actionSaving) return;
            var self = this;
            this.actionSaving = true;
            this.actionError = '';
            window.axios.put('/sales/' + this.editingSale.orderId, {
                table_id: this.editTableId || null,
                notes: this.editNotes,
                payment_mode_id: this.editPaymentModeId || null,
                payment_reference: this.editPaymentReference || null,
                items: this.editItems.map(function (item) {
                    var parts = item.productKey.split(':');
                    return { type: parts[0], id: Number(parts[1]), quantity: Number(item.qty) };
                }),
            }, { headers: { 'X-CSRF-TOKEN': this.csrfToken() } })
                .then(function () { window.location.reload(); })
                .catch(function (error) { self.actionError = self.errorMessage(error, 'Could not update this sale.'); })
                .then(function () { self.actionSaving = false; });
        },

        openBackdate(sale) {
            this.backdateSale = sale;
            this.backdateDate = sale.date.slice(0, 10);
            this.actionError = '';
            this.showBackdateModal = true;
        },

        saveBackdate() {
            if (!this.backdateSale || this.actionSaving) return;
            var self = this;
            this.actionSaving = true;
            this.actionError = '';
            window.axios.patch('/sales/' + this.backdateSale.orderId + '/backdate', {
                date: this.backdateDate,
            }, { headers: { 'X-CSRF-TOKEN': this.csrfToken() } })
                .then(function () { window.location.reload(); })
                .catch(function (error) { self.actionError = self.errorMessage(error, 'Could not change the sale date.'); })
                .then(function () { self.actionSaving = false; });
        },

        openDelete(sale) {
            this.deleteOrderIds = [sale.orderId];
            this.deleteLabel = sale.id;
            this.actionError = '';
            this.showDeleteModal = true;
        },

        openBulkDelete() {
            var self = this;
            var selected = this.sales.filter(function (sale) { return self.selectedSales.indexOf(sale.id) !== -1; });
            if (!selected.length) return;
            this.deleteOrderIds = selected.map(function (sale) { return sale.orderId; });
            this.deleteLabel = selected.length + ' selected sales';
            this.actionError = '';
            this.showDeleteModal = true;
        },

        confirmDelete() {
            if (!this.deleteOrderIds.length || this.actionSaving) return;
            var self = this;
            this.actionSaving = true;
            this.actionError = '';
            var request = this.deleteOrderIds.length === 1
                ? window.axios.delete('/sales/' + this.deleteOrderIds[0], { headers: { 'X-CSRF-TOKEN': this.csrfToken() } })
                : window.axios.post(@json(route('sales.bulk-destroy')), {
                    order_ids: this.deleteOrderIds,
                }, { headers: { 'X-CSRF-TOKEN': this.csrfToken() } });
            request
                .then(function () { window.location.reload(); })
                .catch(function (error) { self.actionError = self.errorMessage(error, 'Could not delete the selected sale(s).'); })
                .then(function () { self.actionSaving = false; });
        },

        csrfToken() {
            var meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        },

        errorMessage(error, fallback) {
            var data = error.response && error.response.data;
            if (data && data.errors) {
                return Object.keys(data.errors).map(function (key) { return data.errors[key][0]; }).join(' ');
            }
            return (data && data.message) || fallback;
        },

        toggleAll(event) {
            if (event.target.checked) {
                this.selectedSales = this.filteredSales.map(function (s) { return s.id; });
            } else {
                this.selectedSales = [];
            }
        },

        toggleSale(saleId, event) {
            if (event.target.checked) {
                if (this.selectedSales.indexOf(saleId) === -1) {
                    this.selectedSales.push(saleId);
                }
            } else {
                this.selectedSales = this.selectedSales.filter(function (id) { return id !== saleId; });
            }
        },

        printBill() {
            var self = this;
            this.billSales = this.sales.filter(function (s) {
                return self.selectedSales.indexOf(s.id) !== -1;
            });
            this.showBillModal = true;
        },

        printDocket() {
            var self = this;
            if (!this.selectedSale) return;
            var saleId = this.selectedSale.id;
            this.docketPrintStatus = 'Sending docket ' + saleId + ' to the receipt printer…';

            window.BmsPrint.print(window.BmsPrint.docketHtml(this.selectedSale), saleId, function (state) {
                if (state === 'printed') {
                    self.docketPrintStatus = 'Docket ' + saleId + ' sent to the receipt printer.';
                } else if (state === 'dialog') {
                    self.docketPrintStatus = 'Select the receipt printer in the print dialog. Tip: launch Chrome with --kiosk-printing to print without any dialog.';
                } else {
                    self.docketPrintStatus = 'The browser blocked printing for docket ' + saleId + '.';
                }
            });
        },

        saveDocketPdf() {
            if (!this.selectedSale) return;
            this.docketPrintStatus = 'Preparing PDF for ' + this.selectedSale.id + '…';
            window.BmsPrint.saveDocketPdf(this.selectedSale);
            this.docketPrintStatus = this.selectedSale.id + '.pdf downloaded.';
        },

        printBillDoc() {
            var self = this;
            if (!this.billSales.length) return;
            this.billPrintStatus = 'Sending the bill to the printer…';

            window.BmsPrint.print(window.BmsPrint.billHtml(this.billSales), 'bill', function (state) {
                if (state === 'printed') {
                    self.billPrintStatus = 'Bill sent to the printer.';
                } else if (state === 'dialog') {
                    self.billPrintStatus = 'Select the receipt printer in the print dialog. Tip: launch Chrome with --kiosk-printing to print without any dialog.';
                } else {
                    self.billPrintStatus = 'The browser blocked printing for this bill.';
                }
            });
        },

        saveBillPdf() {
            this.billPrintStatus = 'Preparing PDF for the bill…';
            window.BmsPrint.saveBillPdf(this.billSales);
            this.billPrintStatus = 'Bill.pdf downloaded.';
        },

        toggleExpand(saleId) {
            this.expandedSale = this.expandedSale === saleId ? null : saleId;
        },

        formatMoney(v) {
            return v.toLocaleString();
        },

        formatDateTime(dt) {
            var d = new Date(dt);
            return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' + d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
        },

        exportSales() {
            this.generatedAt = new Date().toLocaleString();
            window.print();
        },
    };
};
</script>
@endpush

@section('content')
<div x-data="salesData(@js(is_string(request('q')) ? request('q') : ''), @js($sales), @js($salesTodayDate), @js(auth()->user()?->isAdministrator() ?? false), @js($salesProducts), @js($salesTables), @js($salesPaymentModes))" class="pb-6">
    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 no-print">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Sales</h1>
            <p class="mt-1 text-sm text-gray-500">Sales and receipts saved from POS.</p>
        </div>
        <div class="flex items-center gap-3 mt-4 sm:mt-0">
            @if (auth()->user()?->hasPermission('sales', 'export'))
            <button @click="exportSales()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                @heroicon('o-arrow-down-tray', 'w-4 h-4')
                Export Sales
            </button>
            @endif
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6 no-print">
        @if (auth()->user()?->hasPermission('sales', 'view'))
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">Sales Total (Filtered)</p>
            <p class="mt-1.5 text-2xl font-bold tracking-tight text-gray-900"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(filteredTotal)"></span></p>
            <p class="mt-1.5 text-sm text-gray-400"><span x-text="filteredSales.filter(function (sale) { return sale.status === 'paid'; }).length"></span> paid transactions in current filter</p>
        </div>
        @endif
        @if (auth()->user()?->hasPermission('sales', 'profit'))
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">Profit (Filtered)</p>
            <p class="mt-1.5 text-2xl font-bold tracking-tight text-gray-900"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(filteredProfit)"></span></p>
            <p class="mt-1.5 text-sm text-gray-400">Paid sales less estimated product cost</p>
        </div>
        @endif
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-6 no-print">
        <div class="px-5 py-4 border-b border-gray-100">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="relative flex-1 max-w-xs">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" x-model.debounce.300ms="searchQuery" placeholder="Search by ID or table..." class="w-full h-9 pl-9 pr-4 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <select x-model="dateFilter" class="h-9 px-3 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:border-blue-500 outline-none">
                        <option value="all">All Dates</option>
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="week">This Week</option>
                        <option value="custom">Custom Range</option>
                    </select>
                    <template x-if="dateFilter === 'custom'">
                        <div class="flex items-center gap-2 flex-wrap">
                            <input type="date" x-model="dateFrom" class="h-9 px-3 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:border-blue-500 outline-none">
                            <span class="text-xs text-gray-400">to</span>
                            <input type="date" x-model="dateTo" class="h-9 px-3 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:border-blue-500 outline-none">
                            <input type="time" x-model="timeFrom" class="h-9 px-3 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:border-blue-500 outline-none">
                            <span class="text-xs text-gray-400">to</span>
                            <input type="time" x-model="timeTo" class="h-9 px-3 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:border-blue-500 outline-none">
                        </div>
                    </template>
                    <select x-model="counterFilter" class="h-9 px-3 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:border-blue-500 outline-none">
                        <option value="all">All Counters</option>
                        @foreach ($salesCounters as $counter)
                            <option value="{{ $counter }}">{{ $counter }}</option>
                        @endforeach
                    </select>
                    <select x-model="attendantFilter" class="h-9 px-3 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:border-blue-500 outline-none">
                        <option value="all">All Attendants</option>
                        @foreach ($salesAttendants as $attendant)
                            <option value="{{ $attendant }}">{{ $attendant }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-2 ml-auto">
                    @if (auth()->user()?->hasPermission('sales', 'printBill'))
                    <button @click="printBill()" x-show="selectedSales.length > 0"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors shadow-sm shadow-blue-200">
                        @heroicon('o-printer', 'w-4 h-4')
                        Print Bill (<span x-text="selectedSales.length"></span>)
                    </button>
                    @endif
                    @if (auth()->user()?->hasPermission('sales', 'delete'))
                        <button type="button" @click="openBulkDelete()" x-show="selectedSales.length > 0"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors shadow-sm shadow-red-200">
                            @heroicon('o-trash', 'w-4 h-4')
                            Delete (<span x-text="selectedSales.length"></span>)
                        </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Accordion --}}
        <div class="accordion-divider">
            <template x-for="sale in filteredSales" :key="sale.id">
                <div>
                    {{-- Accordion Header --}}
                    <div @click="toggleExpand(sale.id)" class="flex items-center gap-4 px-5 py-3.5 cursor-pointer hover:bg-gray-50/60 transition-colors print-avoid-break">
                        <input type="checkbox" :value="sale.id" @click.stop @change="toggleSale(sale.id, $event)" :checked="selectedSales.indexOf(sale.id) !== -1" class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 no-print">
                        <span class="text-sm font-bold text-gray-900 w-20" x-text="sale.id"></span>
                        <span class="text-xs font-medium text-gray-600 w-28 hidden sm:inline" x-text="sale.counter"></span>
                        <span class="text-sm text-gray-700 w-20 hidden md:inline" x-text="sale.table"></span>
                        <span class="text-xs text-gray-500 hidden sm:inline" x-text="sale.staff"></span>
                        <span class="text-xs text-gray-500 hidden lg:inline" x-text="formatDateTime(sale.date)"></span>
                        <span class="text-sm font-semibold text-gray-900 ml-auto whitespace-nowrap"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(sale.total)"></span></span>
                        <span class="text-xs font-medium px-2 py-0.5 rounded-full" :class="sale.status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'" x-text="sale.status"></span>
                        <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="expandedSale === sale.id ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>

                    {{-- Accordion Body --}}
                    <div x-show="expandedSale === sale.id" x-cloak class="bg-gray-50/80" style="border-top: 1px solid rgba(0,0,0,0.05)"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 -translate-y-1">
                        <div class="px-5 py-4">
                            <div class="flex flex-col sm:flex-row sm:items-start gap-4">
                                {{-- Sale Items --}}
                                <div class="flex-1">
                                    <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Items Purchased</h4>
                                    <div class="bg-white rounded-lg border border-gray-200 accordion-divider">
                                        <template x-for="item in sale.items" :key="item.name">
                                            <div class="flex items-center justify-between px-4 py-2.5">
                                                <div class="flex items-center gap-3">
                                                    <span class="text-sm font-medium text-gray-900" x-text="item.name"></span>
                                                    <span class="text-xs text-gray-500">x<span x-text="item.qty"></span></span>
                                                </div>
                                                <span class="text-sm font-semibold text-gray-900"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(item.price * item.qty)"></span></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                {{-- Sale Summary --}}
                                <div class="sm:w-56 shrink-0">
                                    <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Summary</h4>
                                    <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-2">
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-500">Counter:</span>
                                            <span class="font-medium text-gray-900" x-text="sale.counter"></span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-500">Table:</span>
                                            <span class="font-medium text-gray-900" x-text="sale.table"></span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-500">Staff:</span>
                                            <span class="font-medium text-gray-900" x-text="sale.staff"></span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-500">Items:</span>
                                            <span class="font-medium text-gray-900" x-text="sale.items.length"></span>
                                        </div>
                                        <div class="border-t border-gray-100 pt-2 mt-2">
                                            <div class="flex justify-between text-sm font-bold">
                                                <span class="text-gray-900">Total:</span>
                                                <span class="text-gray-900"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(sale.total)"></span></span>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Actions --}}
                                    <div class="grid grid-cols-1 gap-2 mt-3 no-print">
                                        @if (auth()->user()?->hasPermission('sales', 'reprint'))
                                        <button @click.stop="viewDocket(sale)" class="inline-flex items-center justify-center gap-1 px-2.5 py-2 text-xs font-semibold text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                                            @heroicon('o-document-text', 'w-3.5 h-3.5')
                                            Docket
                                        </button>
                                        @endif
                                        @if (auth()->user()?->hasPermission('sales', 'edit')
                                            || auth()->user()?->hasPermission('sales', 'backdate')
                                            || auth()->user()?->hasPermission('sales', 'delete'))
                                            <div class="flex flex-wrap gap-2">
                                                @if (auth()->user()?->hasPermission('sales', 'edit'))
                                                <button type="button" @click.stop="openEdit(sale)" class="inline-flex items-center justify-center gap-1 rounded-lg bg-emerald-50 px-2 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">
                                                    @heroicon('o-pencil', 'w-3.5 h-3.5')
                                                    Edit
                                                </button>
                                                @endif
                                                @if (auth()->user()?->hasPermission('sales', 'backdate'))
                                                <button type="button" @click.stop="openBackdate(sale)" class="inline-flex items-center justify-center gap-1 rounded-lg bg-amber-50 px-2 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-100">
                                                    @heroicon('o-calendar-days', 'w-3.5 h-3.5')
                                                    Backdate
                                                </button>
                                                @endif
                                                @if (auth()->user()?->hasPermission('sales', 'delete'))
                                                <button type="button" @click.stop="openDelete(sale)" class="inline-flex items-center justify-center gap-1 rounded-lg bg-red-50 px-2 py-2 text-xs font-semibold text-red-700 hover:bg-red-100">
                                                    @heroicon('o-trash', 'w-3.5 h-3.5')
                                                    Delete
                                                </button>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- Empty State --}}
        <template x-if="filteredSales.length === 0">
            <div class="py-12 text-center">
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <p class="text-sm font-medium text-gray-500">No sales found</p>
                <p class="text-xs text-gray-400 mt-1">Try adjusting your filters</p>
            </div>
        </template>
    </div>

    @if (auth()->user()?->hasPermission('sales', 'edit'))
        {{-- Edit Sale --}}
        <template x-teleport="body">
            <div x-show="showEditModal" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4 no-print">
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showEditModal = false"></div>
                <div class="relative z-10 flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">Edit Sale</h3>
                            <p class="text-xs text-gray-500" x-text="editingSale?.id"></p>
                        </div>
                        <button type="button" @click="showEditModal = false" class="p-1 text-gray-400 hover:text-gray-700">@heroicon('o-x-mark', 'w-5 h-5')</button>
                    </div>
                    <div class="space-y-4 overflow-y-auto p-5">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <label class="text-xs font-medium text-gray-600">Table
                                <select x-model="editTableId" class="mt-1 h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                                    <option value="">Takeaway / No table</option>
                                    <template x-for="table in tables" :key="table.id">
                                        <option :value="table.id" x-text="table.name"></option>
                                    </template>
                                </select>
                            </label>
                            <label class="text-xs font-medium text-gray-600">Payment method
                                <select x-model="editPaymentModeId" class="mt-1 h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm">
                                    <option value="">Unspecified</option>
                                    <template x-for="mode in paymentModes" :key="mode.id">
                                        <option :value="mode.id" x-text="mode.name"></option>
                                    </template>
                                </select>
                            </label>
                        </div>
                        <label class="block text-xs font-medium text-gray-600">Payment reference
                            <input type="text" x-model="editPaymentReference" class="mt-1 h-10 w-full rounded-lg border border-gray-200 px-3 text-sm">
                        </label>
                        <label class="block text-xs font-medium text-gray-600">Sale note
                            <textarea x-model="editNotes" rows="2" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"></textarea>
                        </label>
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <h4 class="text-sm font-semibold text-gray-800">Items</h4>
                                <button type="button" @click="addEditItem()" class="rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100">Add item</button>
                            </div>
                            <div class="space-y-2">
                                <template x-for="(item, index) in editItems" :key="index">
                                    <div class="flex items-center gap-2">
                                        <select x-model="item.productKey" @change="updateEditProduct(item)" class="min-w-0 flex-1 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
                                            <template x-for="product in products" :key="product.type + ':' + product.id">
                                                <option :value="product.type + ':' + product.id" x-text="product.name + ' (' + BmsMoney.symbol + ' ' + formatMoney(product.price) + ')'"></option>
                                            </template>
                                        </select>
                                        <input type="number" x-model.number="item.qty" min="1" max="9999" class="w-20 rounded-lg border border-gray-200 px-2 py-2 text-sm" aria-label="Quantity">
                                        <button type="button" @click="editItems.splice(index, 1)" :disabled="editItems.length <= 1" class="rounded-lg p-2 text-red-600 hover:bg-red-50 disabled:opacity-40" aria-label="Remove item">
                                            @heroicon('o-trash', 'w-4 h-4')
                                        </button>
                                    </div>
                                </template>
                            </div>
                            <p class="mt-2 text-[11px] text-gray-500">The server checks stock and recalculates the total using current product prices. If an update fails, no stock or sale changes are saved.</p>
                        </div>
                        <p x-show="actionError" x-cloak x-text="actionError" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>
                    </div>
                    <div class="flex gap-2 border-t border-gray-200 bg-gray-50 px-5 py-4">
                        <button type="button" @click="showEditModal = false" class="flex-1 rounded-xl border border-gray-300 py-2.5 text-sm font-medium text-gray-700">Cancel</button>
                        <button type="button" @click="saveEdit()" :disabled="actionSaving || editItems.length === 0" class="flex-1 rounded-xl bg-blue-600 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50" x-text="actionSaving ? 'Saving…' : 'Save Changes'"></button>
                    </div>
                </div>
            </div>
        </template>

    @endif
    @if (auth()->user()?->hasPermission('sales', 'backdate'))
        {{-- Backdate Sale --}}
        <template x-teleport="body">
            <div x-show="showBackdateModal" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4 no-print">
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showBackdateModal = false"></div>
                <div class="relative z-10 w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl">
                    <h3 class="text-lg font-bold text-gray-900">Backdate Sale</h3>
                    <p class="mt-1 text-sm text-gray-500" x-text="backdateSale?.id"></p>
                    <label class="mt-4 block text-xs font-medium text-gray-600">Sale date
                        <input type="date" x-model="backdateDate" class="mt-1 h-10 w-full rounded-lg border border-gray-200 px-3 text-sm">
                    </label>
                    <p x-show="actionError" x-cloak x-text="actionError" class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>
                    <div class="mt-5 flex gap-2">
                        <button type="button" @click="showBackdateModal = false" class="flex-1 rounded-xl border border-gray-300 py-2.5 text-sm">Cancel</button>
                        <button type="button" @click="saveBackdate()" :disabled="actionSaving || !backdateDate" class="flex-1 rounded-xl bg-amber-500 py-2.5 text-sm font-semibold text-white disabled:opacity-50" x-text="actionSaving ? 'Saving…' : 'Save Date'"></button>
                    </div>
                </div>
            </div>
        </template>

    @endif
    @if (auth()->user()?->hasPermission('sales', 'delete'))
        {{-- Delete Sale --}}
        <template x-teleport="body">
            <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4 no-print">
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showDeleteModal = false"></div>
                <div class="relative z-10 w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl">
                    <h3 class="text-lg font-bold text-gray-900">Delete Sale?</h3>
                    <p class="mt-2 text-sm text-gray-600">Delete <strong x-text="deleteLabel"></strong>? Inventory stock and recipe ingredients consumed by this sale will be returned.</p>
                    <p x-show="actionError" x-cloak x-text="actionError" class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>
                    <div class="mt-5 flex gap-2">
                        <button type="button" @click="showDeleteModal = false" class="flex-1 rounded-xl border border-gray-300 py-2.5 text-sm">Cancel</button>
                        <button type="button" @click="confirmDelete()" :disabled="actionSaving" class="flex-1 rounded-xl bg-red-600 py-2.5 text-sm font-semibold text-white disabled:opacity-50" x-text="actionSaving ? 'Deleting…' : 'Delete & Return Stock'"></button>
                    </div>
                </div>
            </div>
        </template>
    @endif

    {{-- Docket Modal --}}
    <template x-teleport="body">
        <div x-show="showDocketModal"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4 no-print"
             @click.away="showDocketModal = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showDocketModal = false"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm z-10 overflow-hidden"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
                {{-- Docket Content for Printing --}}
                <div class="print-docket px-2" x-show="selectedSale">
                    <div style="text-align:center; border-bottom: 2px dashed #000; padding-bottom: 8px; margin-bottom: 8px;">
                        <div style="font-size: 18px; font-weight: bold; letter-spacing: 1px;" x-text="BmsMoney.bar.receipt_header || BmsMoney.bar.name"></div>
                        <div style="font-size: 12px;" x-text="BmsMoney.bar.address"></div>
                        <div style="font-size: 12px;" x-text="BmsMoney.bar.phone ? 'Tel: ' + BmsMoney.bar.phone : ''"></div>
                    </div>
                    <div style="text-align:center; margin-bottom: 8px;">
                        <div style="font-size: 16px; font-weight: bold; letter-spacing: 2px;">DOCKET</div>
                        <div style="font-size: 14px; font-weight: bold;" x-text="selectedSale?.id"></div>
                        <div style="font-size: 12px;" x-text="selectedSale ? formatDateTime(selectedSale.date) : ''"></div>
                    </div>
                    <div style="border-top: 1px dashed #000; margin: 8px 0;"></div>
                    <div style="font-size: 13px;">
                        <div style="display:flex; justify-content:space-between; margin-bottom: 2px;">
                            <span>Counter:</span>
                            <span style="font-weight: bold;" x-text="selectedSale?.counter"></span>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom: 2px;">
                            <span>Table:</span>
                            <span style="font-weight: bold;" x-text="selectedSale?.table"></span>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom: 2px;">
                            <span>Served by:</span>
                            <span style="font-weight: bold; font-size: 14px;" x-text="selectedSale?.staff"></span>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom: 2px;">
                            <span>Payment:</span>
                            <span style="font-weight: bold;" x-text="selectedSale?.paymentMethod"></span>
                        </div>
                        <div x-show="selectedSale?.paymentRef" style="display:flex; justify-content:space-between; margin-bottom: 2px;">
                            <span>Reference:</span>
                            <span style="font-weight: bold;" x-text="selectedSale?.paymentRef"></span>
                        </div>
                    </div>
                    <div style="border-top: 1px dashed #000; margin: 8px 0;"></div>
                    <div style="font-size: 12px;">
                        <template x-if="selectedSale">
                            <template x-for="item in selectedSale.items" :key="item.name">
                                <div style="display:flex; justify-content:space-between; margin-bottom: 3px;">
                                    <span><span x-text="item.name"></span> x<span x-text="item.qty"></span></span>
                                    <span><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(item.price * item.qty)"></span></span>
                                </div>
                            </template>
                        </template>
                    </div>
                    <div style="border-top: 1px dashed #000; margin: 8px 0;"></div>
                    <div style="font-size: 13px;">
                        <div style="display:flex; justify-content:space-between; margin-bottom: 2px;">
                            <span>Subtotal:</span>
                            <span><span x-text="BmsMoney.symbol"></span> <span x-text="selectedSale ? formatMoney(selectedSale.subtotal) : ''"></span></span>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-weight: bold; font-size: 15px; border-top: 1px solid #000; padding-top: 4px; margin-top: 4px;">
                            <span>TOTAL:</span>
                            <span><span x-text="BmsMoney.symbol"></span> <span x-text="selectedSale ? formatMoney(selectedSale.total) : ''"></span></span>
                        </div>
                    </div>
                    <div style="border-top: 1px dashed #000; margin: 8px 0;"></div>
                    <div x-show="selectedSale?.notes" style="font-size: 12px; border: 1px solid #000; padding: 5px 6px; margin-top: 8px;">
                        <strong>NOTE</strong>
                        <div style="white-space: pre-wrap; word-break: break-word;" x-text="selectedSale?.notes"></div>
                    </div>
                    <div style="text-align:center; font-size: 12px; color: #333;">
                        <div style="margin-top: 8px;" x-text="BmsMoney.bar.receipt_footer || 'Thank you for your purchase!'"></div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 no-print">
                    <div class="flex gap-2">
                        <button @click="showDocketModal = false" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white transition-colors">Close</button>
                        @if (auth()->user()?->hasPermission('sales', 'reprint'))
                        <button @click="saveDocketPdf()" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white transition-colors flex items-center justify-center gap-2">
                            @heroicon('o-arrow-down-tray', 'w-4 h-4')
                            Save PDF
                        </button>
                        <button @click="printDocket()" class="flex-1 py-2.5 rounded-xl bg-blue-600 text-sm font-semibold text-white hover:bg-blue-700 transition-colors shadow-sm shadow-blue-200 flex items-center justify-center gap-2">
                            @heroicon('o-printer', 'w-4 h-4')
                            Print Docket
                        </button>
                        @endif
                    </div>
                    <p x-show="docketPrintStatus" x-cloak x-text="docketPrintStatus"
                       class="mt-2.5 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-lg px-2.5 py-2 leading-relaxed"></p>
                </div>
            </div>
        </div>
    </template>

    {{-- Bill Modal --}}
    <template x-teleport="body">
        <div x-show="showBillModal"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4 no-print"
             @click.away="showBillModal = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showBillModal = false"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm z-10 overflow-hidden max-h-[90vh] overflow-y-auto"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
                {{-- Bill Content for Printing --}}
                <div class="print-bill px-2" x-show="billSales.length > 0">
                    <div style="text-align:center; border-bottom: 2px dashed #000; padding-bottom: 8px; margin-bottom: 8px;">
                        <div style="font-size: 18px; font-weight: bold; letter-spacing: 1px;" x-text="BmsMoney.bar.receipt_header || BmsMoney.bar.name"></div>
                        <div style="font-size: 12px;" x-text="BmsMoney.bar.address"></div>
                        <div style="font-size: 12px;" x-text="BmsMoney.bar.phone ? 'Tel: ' + BmsMoney.bar.phone : ''"></div>
                    </div>
                    <div style="text-align:center; margin-bottom: 8px;">
                        <div style="font-size: 16px; font-weight: bold; letter-spacing: 2px;">BILL</div>
                        <div style="font-size: 12px;" x-text="new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' + new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })"></div>
                    </div>
                    <div style="border-top: 1px dashed #000; margin: 8px 0;"></div>
                    <template x-for="sale in billSales" :key="sale.id">
                        <div style="margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px dashed #ccc;">
                            <div style="font-size: 12px; font-weight: bold; margin-bottom: 4px;">
                                <span x-text="sale.id"></span> - <span x-text="sale.counter"></span> (<span x-text="sale.table"></span>)
                            </div>
                            <div style="font-size: 12px;">
                                <template x-for="item in sale.items" :key="item.name">
                                    <div style="display:flex; justify-content:space-between; margin-bottom: 2px;">
                                        <span><span x-text="item.name"></span> x<span x-text="item.qty"></span></span>
                                        <span><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(item.price * item.qty)"></span></span>
                                    </div>
                                </template>
                            </div>
                            <div style="display:flex; justify-content:space-between; font-size: 12px; font-weight: bold; margin-top: 4px;">
                                <span>Subtotal:</span>
                                <span><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(sale.subtotal)"></span></span>
                            </div>
                            <div style="font-size: 12px; color: #333;">Served by: <span x-text="sale.staff"></span></div>
                        </div>
                    </template>
                    <div style="border-top: 2px solid #000; margin: 8px 0; padding-top: 8px;">
                        <div style="display:flex; justify-content:space-between; font-size: 15px; font-weight: bold;">
                            <span>GRAND TOTAL:</span>
                            <span><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(billTotal)"></span></span>
                        </div>
                    </div>
                    <div style="border-top: 1px dashed #000; margin: 8px 0;"></div>
                    <div style="text-align:center; font-size: 12px; color: #333;">
                        <div style="margin-top: 8px;" x-text="BmsMoney.bar.receipt_footer || 'Thank you for your purchase!'"></div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 no-print">
                    <div class="flex gap-2">
                        <button @click="showBillModal = false" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white transition-colors">Close</button>
                        @if (auth()->user()?->hasPermission('sales', 'printBill'))
                        <button @click="saveBillPdf()" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white transition-colors flex items-center justify-center gap-2">
                            @heroicon('o-arrow-down-tray', 'w-4 h-4')
                            Save PDF
                        </button>
                        <button @click="printBillDoc()" class="flex-1 py-2.5 rounded-xl bg-blue-600 text-sm font-semibold text-white hover:bg-blue-700 transition-colors shadow-sm shadow-blue-200 flex items-center justify-center gap-2">
                            @heroicon('o-printer', 'w-4 h-4')
                        Print Bill
                    </button>
                    @endif
                    </div>
                    <p x-show="billPrintStatus" x-cloak x-text="billPrintStatus"
                       class="mt-2.5 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-lg px-2.5 py-2 leading-relaxed"></p>
                </div>
            </div>
        </div>
    </template>

    {{-- ===== PRINT-ONLY SALES REPORT ===== --}}
    <div class="print-report">
        <div style="text-align:center; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 10px;">
            <div style="font-size: 18px; font-weight: bold; letter-spacing: 1px;" x-text="BmsMoney.bar.name"></div>
            <div style="font-size: 12px;"><span x-text="BmsMoney.bar.address"></span><span x-show="BmsMoney.bar.phone"> · Tel: <span x-text="BmsMoney.bar.phone"></span></span></div>
        </div>
        <div style="text-align:center; border-bottom: 1px solid #999; margin-bottom: 10px; padding-bottom: 6px;">
            <h1 style="font-size: 16px; font-weight: bold; letter-spacing: 2px; margin: 0 0 3px;">SALES REPORT</h1>
            <p style="font-size: 12px; margin: 0;" x-text="'Generated: ' + generatedAt"></p>
        </div>
        <p style="font-size: 11px; margin: 0 0 10px;" x-text="printFilterSummary"></p>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date &amp; Time</th>
                    <th>Counter</th>
                    <th>Table</th>
                    <th>Attendant</th>
                    <th style="text-align:right;">Items</th>
                    <th style="text-align:right;">Total (<span x-text="BmsMoney.symbol"></span>)</th>
                    <th style="text-align:center;">Status</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="sale in filteredSales" :key="sale.id">
                    <tr>
                        <td style="font-weight:bold;" x-text="sale.id"></td>
                        <td x-text="formatDateTime(sale.date)"></td>
                        <td x-text="sale.counter"></td>
                        <td x-text="sale.table"></td>
                        <td x-text="sale.staff"></td>
                        <td style="text-align:right;" x-text="sale.items.length"></td>
                        <td style="text-align:right;" x-text="formatMoney(sale.total)"></td>
                        <td style="text-align:center; text-transform:capitalize;" x-text="sale.status"></td>
                    </tr>
                </template>
            </tbody>
        </table>
        <div style="margin-top: 12px; border-top: 2px solid #000; padding-top: 8px; display:flex; justify-content:space-between; font-weight:bold; font-size: 13px;">
            <span>Total Sales: <span x-text="filteredSales.length"></span></span>
            <span>Grand Total: <span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(filteredTotal)"></span></span>
        </div>
    </div>

</div>
@endsection
