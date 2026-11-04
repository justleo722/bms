@extends('layouts.master', ['activeNav' => $activeNav ?? 'pos'])

@section('title', 'POS Terminal')

@push('head')
@include('partials.receipt-print')
<script>
</script>
<script>
window.posData = function (initialQuery) {
    var counters = @json($posCounters);
    return {
        activeCategory: 'all',
        searchQuery: initialQuery || '',
        cart: [],
        selectedTable: 'Select Table',
        selectedCounter: counters.length ? counters[0].id : null,
        orderNotes: '',
        showTableModal: false,
        showNotesModal: false,
        showPaymentModal: false,
        showMobileCart: false,
        savingSale: false,
        activeTab: 'all',

        counters: counters,
        categories: [{ id: 'all', label: 'All' }].concat(@json($posCategories)),
        products: @json($posProducts),
        rawMaterials: @json($posRawMaterials),
        recipes: @json($posRecipes),
        consumptionLog: [],

        getFoodRecipe(product) {
            if (!product.recipeId) return null;
            return this.recipes.find(function(r){ return r.id === product.recipeId; }) || null;
        },

        canFulfillOrder(product, qty) {
            if (!product.productionMethod || product.productionMethod === 'stock-item') return { canFulfill: true, missing: [] };
            var recipe = this.getFoodRecipe(product);
            if (!recipe) return { canFulfill: true, missing: [] };
            var self = this;
            var missing = [];
            recipe.ingredients.forEach(function(ing) {
                var mat = self.rawMaterials.find(function(m){ return m.id === ing.materialId; });
                if (!mat) { missing.push({ name: ing.name, needed: 0, available: 0, unit: '' }); return; }
                var perServing = (parseFloat(ing.consumptionQty) || 0) / Math.max(parseFloat(recipe.outputQty) || 1, 0.0001);
                var wasteMult = 1 + ((parseFloat(ing.wastePct) || 0) / 100);
                var totalNeeded = perServing * qty * wasteMult;
                if (mat.currentQty < totalNeeded) {
                    missing.push({ name: mat.name, needed: totalNeeded, available: mat.currentQty, unit: mat.unit });
                }
            });
            return { canFulfill: missing.length === 0, missing: missing };
        },

        consumeIngredients(product, qty) {
            var recipe = this.getFoodRecipe(product);
            if (!recipe) return { success: true, consumed: [] };
            var self = this;
            var consumed = [];
            var errors = [];
            recipe.ingredients.forEach(function(ing) {
                var mat = self.rawMaterials.find(function(m){ return m.id === ing.materialId; });
                if (!mat) { errors.push(ing.name + ' not found'); return; }
                var perServing = (parseFloat(ing.consumptionQty) || 0) / Math.max(parseFloat(recipe.outputQty) || 1, 0.0001);
                var wasteMult = 1 + ((parseFloat(ing.wastePct) || 0) / 100);
                var totalNeeded = perServing * qty * wasteMult;
                if (mat.currentQty < totalNeeded) {
                    errors.push(ing.name + ' insufficient');
                    return;
                }
                mat.currentQty = Math.round((mat.currentQty - totalNeeded) * 10000) / 10000;
                consumed.push({ name: mat.name, deducted: totalNeeded, unit: mat.unit, remaining: mat.currentQty });
            });
            if (errors.length === 0) {
                self.consumptionLog.unshift({
                    id: Date.now(), product: product.name, qty: qty, items: consumed,
                    timestamp: new Date().toLocaleTimeString(), type: 'pos-sale'
                });
            }
            return { success: errors.length === 0, consumed: consumed, errors: errors };
        },

        formatQtyUnit(qty, unit) {
            if (unit === 'kg' && qty < 1) return Math.round(qty * 1000) + 'g';
            if (unit === 'L' && qty < 1) return Math.round(qty * 1000) + 'ml';
            return parseFloat(qty.toFixed(2)) + ' ' + unit;
        },

        showStockWarning: false,
        stockWarningItems: [],
        showOrderConfirm: false,
        pendingOrderItems: [],

        // ========== PAYMENT MODES (managed in Settings > Payment Methods) ==========
        paymentModes: @json($posPaymentModes),
        selectedPaymentMode: null,
        paymentRef: '',
        paymentError: '',
        get activePaymentModes() {
            return this.paymentModes.filter(function (m) { return m.enabled; });
        },
        paymentModeById(id) {
            return this.paymentModes.find(function (m) { return m.id === id; }) || null;
        },
        get selectedPaymentModeObj() { return this.paymentModeById(this.selectedPaymentMode); },
        loadPaymentModes(modes) {
            if (Array.isArray(modes)) this.paymentModes = modes;
            var stillValid = this.activePaymentModes.some(function (m) { return m.id === this.selectedPaymentMode; }.bind(this));
            if (!stillValid) this.selectedPaymentMode = this.activePaymentModes.length ? this.activePaymentModes[0].id : null;
        },
        selectPaymentMode(id) {
            this.selectedPaymentMode = id;
            this.paymentError = '';
        },

        // ========== SALE COMPLETION / DIRECT THERMAL PRINT ==========
        lastSale: null,
        toast: { show: false, text: '', tone: 'info' },
        toastTimer: null,
        csrfToken() {
            var meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        },
        counterName(id) {
            var counter = this.counters.find(function (item) { return Number(item.id) === Number(id); });
            return counter ? counter.name : '';
        },
        notify(text, tone) {
            var self = this;
            this.toast = { show: true, text: text, tone: tone || 'info' };
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(function () { self.toast.show = false; }, 6000);
        },
        printDocket() {
            var self = this;
            if (!this.lastSale) { this.notify('No completed sale to print.', 'error'); return; }
            var saleId = this.lastSale.id;

            window.BmsPrint.print(
                window.BmsPrint.docketHtml(this.lastSale),
                saleId,
                function (state) {
                    if (state === 'printed') {
                        self.notify('Docket ' + saleId + ' sent to the receipt printer.', 'success');
                    } else if (state === 'dialog') {
                        self.notify('Select the receipt printer in the print dialog. Tip: launch Chrome with --kiosk-printing to print without any dialog.', 'info');
                    } else {
                        self.notify('The browser blocked printing for docket ' + saleId + '.', 'error');
                    }
                }
            );
        },

        tables: @json($posTables),

        get filteredProducts() {
            var q = this.searchQuery.toLowerCase().trim();
            var self = this;
            return this.products.filter(function (p) {
                var catMatch = self.activeCategory === 'all' || p.category === self.activeCategory;
                p.stock = p.type === 'inventory' ? Number(p.stockByCounter[String(self.selectedCounter)] || 0) : 999999;
                var counterMatch = p.type !== 'inventory' || p.stockByCounter[String(self.selectedCounter)] !== undefined;
                if (!q) { return catMatch && counterMatch; }

                var searchMatch = String(p.name || '').toLowerCase().includes(q)
                    || String(p.category || '').toLowerCase().includes(q)
                    || String(p.categoryLabel || '').toLowerCase().includes(q);

                return catMatch && searchMatch && counterMatch;
            });
        },

        get cartItemCount() {
            return this.cart.reduce(function (sum, item) { return sum + item.qty; }, 0);
        },

        get cartSubtotal() {
            return this.cart.reduce(function (sum, item) { return sum + (item.price * item.qty); }, 0);
        },

        get cartTotal() {
            return this.cartSubtotal;
        },

        addToCart(product) {
            var existing = this.cart.find(function (item) { return item.id === product.id; });
            var newQty = existing ? existing.qty + 1 : 1;

            if (product.productionMethod === 'make-to-order') {
                var check = this.canFulfillOrder(product, newQty);
                if (!check.canFulfill) {
                    this.stockWarningItems = check.missing;
                    this.showStockWarning = true;
                    return;
                }
            } else if (product.type === 'inventory' && product.stock !== undefined) {
                if (newQty > product.stock) return;
            }

            if (existing) {
                existing.qty = newQty;
            } else {
                this.cart.push({ id: product.id, sourceId: product.sourceId, type: product.type, name: product.name, price: product.price, qty: 1, stock: product.stock, image: product.image, category: product.category, productionMethod: product.productionMethod || null, recipeId: product.recipeId || null });
            }
        },

        removeItem(index) {
            this.cart.splice(index, 1);
        },

        updateQty(index, delta) {
            var item = this.cart[index];
            var newQty = item.qty + delta;
            if (newQty <= 0) { this.removeItem(index); return; }
            if (item.productionMethod === 'make-to-order') {
                var product = this.products.find(function(p){ return p.id === item.id; });
                if (product) {
                    var check = this.canFulfillOrder(product, newQty);
                    if (!check.canFulfill) {
                        this.stockWarningItems = check.missing;
                        this.showStockWarning = true;
                        return;
                    }
                }
            } else if (item.type === 'inventory' && item.stock !== undefined && newQty > item.stock) return;
            item.qty = newQty;
        },

        clearCart() {
            this.cart = [];
            this.orderNotes = '';
        },

        // ========== CLEAR CART CONFIRMATION ==========
        showClearCartModal: false,

        askClearCart() {
            if (!this.cart.length) { return; }
            this.showClearCartModal = true;
        },

        closeClearCart() {
            this.showClearCartModal = false;
        },

        confirmClearCart() {
            this.clearCart();
            this.showClearCartModal = false;
        },

        setCategory(cat) {
            this.activeCategory = cat;
            this.activeTab = cat;
        },

        isProductImage(image) {
            return typeof image === 'string' && /^(data:image\/|https?:\/\/|\/)/i.test(image);
        },

        get productGridCols() {
            return 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6';
        },

        proceedPayment() {
            if (this.cart.length === 0) return;

            var self = this;
            var allMissing = [];
            this.cart.forEach(function(item) {
                if (item.productionMethod === 'make-to-order') {
                    var check = self.canFulfillOrder(item, item.qty);
                    if (!check.canFulfill) {
                        check.missing.forEach(function(m) { m.itemName = item.name; allMissing.push(m); });
                    }
                }
            });

            if (allMissing.length > 0) {
                this.stockWarningItems = allMissing;
                this.showStockWarning = true;
                return;
            }

            this.loadPaymentModes();
            this.paymentRef = '';
            this.paymentError = '';

            // Payment mode is optional: with none active, save the sale and print the docket directly
            if (this.activePaymentModes.length === 0) {
                this.completeSale(null);
                return;
            }

            this.showPaymentModal = true;
        },

        confirmPayment(savePdfOnly) {
            var mode = this.selectedPaymentModeObj;
            if (mode && mode.requiresRef && !this.paymentRef.trim()) {
                this.paymentError = 'Enter the ' + (mode.refLabel || 'reference') + ' for ' + mode.name + '.';
                return;
            }
            this.completeSale(mode, !!savePdfOnly);
        },

        completeSale(mode, savePdfOnly) {
            if (this.savingSale) return;
            this.savingSale = true;
            var self = this;
            window.axios.post(@json(route('pos.checkout')), {
                counter_id: this.selectedCounter,
                table: this.selectedTable === 'Select Table' ? null : this.selectedTable,
                notes: this.orderNotes,
                payment_mode_id: mode ? mode.id : null,
                payment_reference: mode && mode.requiresRef ? this.paymentRef.trim() : null,
                items: this.cart.map(function (item) {
                    return { type: item.type, id: item.sourceId, quantity: item.qty };
                }),
            }, { headers: { 'X-CSRF-TOKEN': this.csrfToken() } })
                .then(function (response) {
                    var sale = response.data.sale;
                    self.lastSale = sale;
                    self.paymentError = '';
                    self.showPaymentModal = false;
                    self.clearCart();
                    if (savePdfOnly) self.saveDocketPdf();
                    else self.printDocket();
                })
                .catch(function (error) {
                    var data = error.response && error.response.data;
                    self.paymentError = data && data.errors
                        ? Object.keys(data.errors).map(function (key) { return data.errors[key][0]; }).join(' ')
                        : (data && data.message) || 'Could not save this docket.';
                    self.notify(self.paymentError, 'error');
                })
                .then(function () { self.savingSale = false; });
        },
        saveDocketPdf() {
            if (!this.lastSale) { this.notify('No completed sale to save.', 'error'); return; }
            window.BmsPrint.saveDocketPdf(this.lastSale);
            this.notify('Docket ' + this.lastSale.id + ' saved as PDF.', 'success');
        },

        stockTimer: null,
        init() {
            var self = this;
            this.stockTimer = setInterval(function () { self.refreshStockLevels(); }, 25000);
        },
        refreshStockLevels() {
            if (document.hidden) return;
            var self = this;
            var counter = this.selectedCounter;
            fetch('/pos/stock-levels?counter_id=' + encodeURIComponent(counter || ''), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (data) {
                    if (!data) return;
                    (data.stocks || []).forEach(function (row) {
                        self.products.forEach(function (product) {
                            if (product.type === 'inventory' && Number(product.sourceId) === Number(row.productId)) {
                                product.stockByCounter[String(row.counterId)] = row.quantity;
                            }
                        });
                    });
                    (data.materials || []).forEach(function (row) {
                        var material = self.rawMaterials.find(function (item) { return Number(item.id) === Number(row.id); });
                        if (material) material.currentQty = row.quantity;
                    });
                })
                .catch(function () {
                    // Background refresh failures must not disturb a sale in progress.
                });
        },

        formatMoney(v) {
            return v.toLocaleString();
        },
    };
};
</script>
<style>
.pos-scroll::-webkit-scrollbar { width: 4px; }
.pos-scroll::-webkit-scrollbar-track { background: transparent; }
.pos-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.pos-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

.pos-sidebar-scroll::-webkit-scrollbar { width: 3px; }
.pos-sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
.pos-sidebar-scroll::-webkit-scrollbar-thumb { background: #475569; border-radius: 4px; }
</style>
@endpush

@section('content')
<div x-data="posData(@js(is_string(request('q')) ? request('q') : ''))"
     x-init="
        loadPaymentModes();
        window.addEventListener('bms-pos-search', function (e) { searchQuery = e.detail || ''; });
        // Typing must not wipe what was typed: only a deliberate category
        // change resets the search box.
        let searchDriven = false;
        $watch('searchQuery', function (val) {
            if (val && activeCategory !== 'all') {
                searchDriven = true;
                activeCategory = 'all';
            }
        });
        $watch('activeCategory', function () {
            activeTab = activeCategory;
            if (searchDriven) { searchDriven = false; return; }
            searchQuery = '';
        });
     "
     class="fixed inset-x-0 z-20 bg-gray-50 overflow-hidden"
     style="top: 64px; bottom: 68px;">

    <div class="flex h-full">

        {{-- ===== LEFT SIDEBAR ===== --}}
        <aside class="hidden lg:flex flex-col w-[220px] xl:w-[240px] flex-shrink-0 bg-slate-800 overflow-hidden">
            <div class="px-4 py-4 border-b border-slate-700/50">
                <h2 class="text-sm font-semibold text-slate-300 tracking-wider uppercase">Categories</h2>
            </div>
            <nav class="flex-1 overflow-y-auto py-2 pos-sidebar-scroll">
                <template x-for="cat in categories" :key="cat.id">
                    <button @click="setCategory(cat.id)"
                            :class="activeCategory === cat.id ? 'bg-slate-700 text-white border-l-4 border-amber-400' : 'text-slate-300 hover:text-white hover:bg-slate-700/50 border-l-4 border-transparent'"
                            class="flex items-center gap-3 w-full px-4 py-3 text-sm font-medium transition-all duration-150">
                        <span class="truncate font-semibold" x-text="cat.label"></span>
                    </button>
                </template>
            </nav>
            <div class="px-4 py-3 border-t border-slate-700/50">
            </div>
        </aside>

        {{-- Mobile sidebar toggle --}}
        <div class="lg:hidden flex-shrink-0 flex flex-col bg-slate-800 w-20 overflow-y-auto">
            <button @click="activeCategory = 'all'"
                    :class="activeCategory === 'all' ? 'bg-slate-700 text-amber-400' : 'text-slate-400 hover:text-white hover:bg-slate-700/50'"
                    class="flex items-center justify-center min-h-14 px-1 text-center text-xs font-medium transition-colors">
                All
            </button>
            <template x-for="cat in categories.filter(function(c){return c.id !== 'all';})" :key="cat.id">
                <button @click="setCategory(cat.id)"
                        :class="activeCategory === cat.id ? 'bg-slate-700 text-amber-400' : 'text-slate-400 hover:text-white hover:bg-slate-700/50'"
                        class="flex items-center justify-center min-h-14 px-1 text-center text-xs font-medium transition-colors">
                    <span x-text="cat.label"></span>
                </button>
            </template>
        </div>

        {{-- ===== CENTER AREA ===== --}}
        <main class="flex-1 min-w-0 flex flex-col bg-gray-50 overflow-hidden">
            {{-- Counter Selector --}}
            <div class="flex-shrink-0 px-4 py-2.5 bg-white border-b border-gray-200">
                <div class="flex items-center gap-3">
                    <span class="text-xs font-medium text-gray-500 uppercase tracking-wider flex-shrink-0">Counter:</span>
                    <div class="flex items-center gap-2 overflow-x-auto no-scrollbar">
                        <template x-for="counter in counters" :key="counter.id">
                            <button @click="selectedCounter = counter.id"
                                    :class="Number(selectedCounter) === Number(counter.id) ? 'bg-amber-500 text-white shadow-sm shadow-amber-200' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                    class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-150 whitespace-nowrap">
                                <span x-text="counter.name"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Product Grid --}}
            <div class="flex-1 overflow-y-auto px-4 py-4 pos-scroll">
                <template x-if="filteredProducts.length === 0">
                    <div class="flex flex-col items-center justify-center h-full text-center py-12">
                        <span class="text-4xl mb-3">🔍</span>
                        <p class="text-sm font-medium text-gray-500">No products found</p>
                        <p class="text-xs text-gray-400 mt-1">Try a different category or search term</p>
                    </div>
                </template>
                <div :class="productGridCols" class="grid gap-2">
                    <template x-for="product in filteredProducts" :key="product.id">
                        <div @click="if (product.type !== 'inventory' || product.stock > 0) addToCart(product)"
                             :class="product.type === 'inventory' && product.stock <= 0 ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:shadow-md hover:border-blue-200 hover:-translate-y-0.5 active:scale-[0.98]'"
                             class="group relative bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden transition-all duration-200">
                            <div class="h-24 sm:h-28 bg-gradient-to-br from-gray-50 to-gray-100 flex items-center justify-center overflow-hidden">
                                <template x-if="isProductImage(product.image)">
                                    <img :src="product.image" :alt="product.name" class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105">
                                </template>
                                <template x-if="!isProductImage(product.image)">
                                    <span class="text-3xl text-gray-300">@heroicon('o-photo', 'w-8 h-8')</span>
                                </template>
                            </div>
                            <div class="p-2">
                                <h3 class="text-xs font-semibold text-gray-900 truncate" x-text="product.name"></h3>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-[10px] font-medium text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded" x-text="product.categoryLabel"></span>
                                    <template x-if="product.productionMethod === 'make-to-order'">
                                        <span class="text-[10px] font-medium text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded ring-1 ring-blue-600/20">Made Fresh</span>
                                    </template>
                                </div>
                                <div class="flex items-center justify-between mt-1.5">
                                    <span class="text-sm font-bold text-gray-900"><span x-text="BmsMoney.symbol"></span> <span x-text="product.price.toLocaleString()"></span></span>
                                    <template x-if="product.productionMethod === 'make-to-order'">
                                        <span class="text-[10px] font-medium text-blue-600" x-text="canFulfillOrder(product, 1).canFulfill ? '✓ Available' : '✗ Out of Stock'"></span>
                                    </template>
                                    <template x-if="!product.productionMethod || product.productionMethod === 'stock-item'">
                                        <span class="text-[10px] font-medium" :class="product.stock <= 5 ? 'text-red-500' : 'text-gray-400'">
                                            <span x-text="product.stock"></span> left
                                        </span>
                                    </template>
                                    <template x-if="product.productionMethod === 'batch-production'">
                                        <span class="text-[10px] font-medium text-purple-600">Batch</span>
                                    </template>
                                </div>
                            </div>
                            <div class="absolute inset-0 ring-1 ring-inset ring-black/0 group-hover:ring-blue-200 rounded-xl pointer-events-none transition-all duration-200"></div>
                        </div>
                    </template>
                </div>
            </div>
        </main>

        {{-- ===== RIGHT PANEL ===== --}}
        <aside class="hidden lg:flex flex-col w-[360px] xl:w-[400px] flex-shrink-0 bg-white border-l border-gray-200 overflow-hidden">

            {{-- Order Header --}}
            <div class="flex-shrink-0 px-5 py-4 border-b border-gray-200 bg-white">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-base font-bold text-gray-900">Current Order</h2>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-medium text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full" x-text="counterName(selectedCounter)"></span>
                        <button @click="askClearCart()" class="text-xs text-gray-400 hover:text-red-500 font-medium transition-colors">Clear</button>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button @click="showTableModal = true" class="flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded-lg text-xs font-medium text-gray-700 transition-colors">
                        <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                        <span x-text="selectedTable"></span>
                        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <button @click="showNotesModal = true" class="relative flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors" :class="orderNotes.trim() ? 'bg-amber-100 text-amber-800 ring-2 ring-amber-400' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'">
                        <svg class="w-3.5 h-3.5" :class="orderNotes.trim() ? 'text-amber-600' : 'text-gray-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        <span x-text="orderNotes.trim() ? 'Note added' : 'Add note'"></span>
                        <template x-if="orderNotes.trim()">
                            <span class="absolute -top-1 -right-1 w-2 h-2 rounded-full bg-amber-500"></span>
                        </template>
                    </button>
                </div>
            </div>

            {{-- Active Note Preview --}}
            <div x-show="orderNotes.trim()" x-cloak class="flex-shrink-0 px-5 py-2.5 bg-amber-50 border-b border-amber-200">
                <div class="flex items-start gap-2">
                    <svg class="w-3.5 h-3.5 flex-shrink-0 mt-px text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    <p class="text-xs text-amber-900 leading-snug flex-1 min-w-0 break-words" x-text="orderNotes"></p>
                    <button @click="orderNotes = ''" class="flex-shrink-0 text-amber-500 hover:text-amber-700 transition-colors" title="Remove note">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            {{-- Cart Items --}}
            <div class="flex-1 overflow-y-auto pos-scroll">
                <template x-if="cart.length === 0">
                    <div class="flex flex-col items-center justify-center h-full text-center px-6">
                        <svg class="w-16 h-16 text-gray-200 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                        <p class="text-sm font-medium text-gray-400">Order is empty</p>
                        <p class="text-xs text-gray-300 mt-1">Tap products to add items</p>
                    </div>
                </template>
                <template x-for="(item, index) in cart" :key="item.id">
                    <div class="flex items-center gap-3 px-5 py-3 border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                        <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center text-lg flex-shrink-0">
                            <template x-if="isProductImage(item.image)">
                                <img :src="item.image" :alt="item.name" class="h-full w-full rounded-lg object-cover">
                            </template>
                            <template x-if="!isProductImage(item.image)">
                                <span class="text-gray-400">@heroicon('o-photo', 'w-4 h-4')</span>
                            </template>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate" x-text="item.name"></p>
                            <p class="text-xs text-gray-500"><span x-text="BmsMoney.symbol"></span> <span x-text="item.price.toLocaleString()"></span> ea</p>
                        </div>
                        <div class="flex items-center gap-1 flex-shrink-0">
                            <button @click="updateQty(index, -1)" class="w-7 h-7 flex items-center justify-center rounded-md border border-gray-200 text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition-colors text-sm font-medium">&minus;</button>
                            <span class="w-8 text-center text-sm font-bold text-gray-900" x-text="item.qty"></span>
                            <button @click="updateQty(index, 1)" class="w-7 h-7 flex items-center justify-center rounded-md border border-gray-200 text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition-colors text-sm font-medium" :disabled="item.qty >= item.stock">+</button>
                        </div>
                        <div class="text-right flex-shrink-0 min-w-[80px]">
                            <p class="text-sm font-bold text-gray-900"><span x-text="BmsMoney.symbol"></span> <span x-text="(item.price * item.qty).toLocaleString()"></span></p>
                        </div>
                        <button @click="removeItem(index)" class="flex-shrink-0 p-1 text-gray-300 hover:text-red-500 transition-colors">
                            @heroicon('o-trash', 'w-4 h-4')
                        </button>
                    </div>
                </template>
            </div>

            {{-- Order Summary --}}
            <div class="flex-shrink-0 border-t border-gray-200 bg-white">
                <div class="px-5 py-3 space-y-1.5">
                    <div class="flex items-center justify-between">
                        <span class="text-base font-bold text-gray-900">Total</span>
                        <span class="text-xl font-bold text-gray-900"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(cartTotal)"></span></span>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="px-5 py-4 border-t border-gray-200">
                    <button @click="proceedPayment"
                            :disabled="cart.length === 0"
                            class="w-full px-3 py-2.5 rounded-lg bg-gradient-to-r from-emerald-600 to-emerald-500 text-white text-sm font-semibold shadow-sm shadow-emerald-200 hover:from-emerald-700 hover:to-emerald-600 disabled:opacity-40 disabled:cursor-not-allowed transition-all duration-200">
                        Save
                    </button>
                </div>
            </div>
        </aside>

        {{-- Mobile cart toggle --}}
        <div class="lg:hidden fixed bottom-[76px] right-4 z-30">
            <button @click="showMobileCart = true"
                    class="flex items-center gap-2 px-5 py-3 bg-gradient-to-r from-blue-600 to-blue-500 text-white rounded-full shadow-lg shadow-blue-200 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                <span class="text-sm font-semibold">Cart</span>
                <span x-show="cartItemCount > 0"
                      x-text="cartItemCount"
                      class="bg-white/20 text-white text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[22px] text-center">
                </span>
            </button>
        </div>

        {{-- ===== MOBILE CART BOTTOM SHEET ===== --}}
        <template x-teleport="body">
            <div x-show="showMobileCart"
                 x-cloak
                 class="fixed inset-0 z-50 lg:hidden"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0">
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showMobileCart = false"></div>
                <div class="fixed inset-x-0 bottom-0 bg-white rounded-t-2xl shadow-2xl z-50 max-h-[85vh] flex flex-col"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="translate-y-full"
                     x-transition:enter-end="translate-y-0"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="translate-y-0"
                     x-transition:leave-end="translate-y-full">
                    <div class="flex justify-center pt-3 pb-2">
                        <div class="w-10 h-1 rounded-full bg-gray-300"></div>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200">
                        <div>
                            <h3 class="text-base font-bold text-gray-900">Current Order</h3>
                            <p class="text-xs text-gray-500"><span x-text="counterName(selectedCounter)"></span> &middot; <span x-text="selectedTable"></span> &middot; <span x-text="cartItemCount"></span> items</p>
                        </div>
                        <button @click="showMobileCart = false" class="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="flex-1 overflow-y-auto px-5 py-3">
                        <div class="mb-2">
                            <button @click="showMobileCart = false; showNotesModal = true"
                                    class="w-full flex items-center gap-2 px-3 py-2.5 rounded-xl text-xs font-medium transition-colors text-left"
                                    :class="orderNotes.trim() ? 'bg-amber-50 text-amber-800 ring-1 ring-amber-300' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'">
                                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                <span class="flex-1 min-w-0 truncate" x-text="orderNotes.trim() || 'Add order note'"></span>
                            </button>
                        </div>
                        <template x-if="cart.length === 0">
                            <div class="flex flex-col items-center justify-center py-8 text-center">
                                <svg class="w-12 h-12 text-gray-200 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                                <p class="text-sm font-medium text-gray-400">Order is empty</p>
                            </div>
                        </template>
                        <template x-for="(item, index) in cart" :key="item.id">
                            <div class="flex items-center gap-3 py-3 border-b border-gray-50">
                                <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center text-lg flex-shrink-0">
                                    <template x-if="isProductImage(item.image)">
                                        <img :src="item.image" :alt="item.name" class="h-full w-full rounded-lg object-cover">
                                    </template>
                                    <template x-if="!isProductImage(item.image)">
                                        <span class="text-gray-400">@heroicon('o-photo', 'w-4 h-4')</span>
                                    </template>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 truncate" x-text="item.name"></p>
                                    <p class="text-xs text-gray-500"><span x-text="BmsMoney.symbol"></span> <span x-text="item.price.toLocaleString()"></span> ea</p>
                                </div>
                                <div class="flex items-center gap-1 flex-shrink-0">
                                    <button @click="updateQty(index, -1)" class="w-7 h-7 flex items-center justify-center rounded-md border border-gray-200 text-gray-500 hover:bg-gray-100 text-sm">&minus;</button>
                                    <span class="w-8 text-center text-sm font-bold text-gray-900" x-text="item.qty"></span>
                                    <button @click="updateQty(index, 1)" class="w-7 h-7 flex items-center justify-center rounded-md border border-gray-200 text-gray-500 hover:bg-gray-100 text-sm" :disabled="item.qty >= item.stock">+</button>
                                </div>
                                <div class="text-right flex-shrink-0 min-w-[70px]">
                                    <p class="text-sm font-bold text-gray-900"><span x-text="BmsMoney.symbol"></span> <span x-text="(item.price * item.qty).toLocaleString()"></span></p>
                                </div>
                                <button @click="removeItem(index)" class="p-1 text-gray-300 hover:text-red-500">
                                    @heroicon('o-trash', 'w-4 h-4')
                                </button>
                            </div>
                        </template>
                    </div>
                    <div class="border-t border-gray-200 px-5 py-3 space-y-1.5 bg-white">
                        <div class="flex items-center justify-between">
                            <span class="text-base font-bold text-gray-900">Total</span>
                            <span class="text-xl font-bold text-gray-900"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(cartTotal)"></span></span>
                        </div>
                    </div>
                    <div class="px-5 py-4 border-t border-gray-200">
                        <button @click="showMobileCart = false; proceedPayment();" :disabled="cart.length === 0" class="w-full py-3 rounded-xl bg-gradient-to-r from-emerald-600 to-emerald-500 text-sm font-semibold text-white disabled:opacity-40 disabled:cursor-not-allowed shadow-sm shadow-emerald-200">
                            Save <span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(cartTotal)"></span>
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- Table Modal --}}
    <template x-teleport="body">
        <div x-show="showTableModal"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             @click.away="showTableModal = false">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showTableModal = false"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 z-10">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">Select Table</h3>
                    <button @click="showTableModal = false" class="p-1 text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="grid grid-cols-4 gap-2">
                    <template x-for="table in tables" :key="table">
                        <button @click="selectedTable = table; showTableModal = false"
                                :class="selectedTable === table ? 'bg-blue-600 text-white ring-2 ring-blue-200' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                class="py-3 rounded-xl text-xs font-medium transition-all duration-150">
                            <span x-text="table"></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </template>

    {{-- Notes Modal --}}
    <template x-teleport="body">
        <div x-show="showNotesModal"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             x-effect="if (showNotesModal) $nextTick(() => { var el = $refs.noteBox; if (el) { el.focus(); el.setSelectionRange(el.value.length, el.value.length); } })"
             @click.away="showNotesModal = false">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showNotesModal = false"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 z-10">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">Order Note</h3>
                    <button @click="showNotesModal = false" class="p-1 text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <textarea x-model="orderNotes" x-ref="noteBox" placeholder="e.g. Guest is allergic to shellfish, no ice, bill to be split..."
                          class="w-full h-28 px-4 py-3 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 outline-none transition-all resize-none placeholder:text-gray-400"></textarea>
                <p class="mt-2 text-[11px] text-gray-400 leading-snug">
                    Printed at the bottom of the docket. Leave it empty for a normal docket.
                </p>
                <div class="flex gap-2 mt-4">
                    <button x-show="orderNotes.trim()" @click="orderNotes = ''" class="px-4 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">Remove</button>
                    <button @click="showNotesModal = false" class="flex-1 px-6 py-2.5 rounded-xl bg-amber-500 text-sm font-semibold text-white hover:bg-amber-600 transition-colors shadow-sm shadow-amber-200">Done</button>
                </div>
            </div>
        </div>
    </template>

    {{-- Payment Modal --}}
    <template x-teleport="body">
        <div x-show="showPaymentModal"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="showPaymentModal = false"></div>
            <div class="relative z-10 flex flex-col w-full max-w-md bg-white rounded-2xl shadow-2xl max-h-[90vh] overflow-hidden"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">

                {{-- Header --}}
                <div class="flex-shrink-0 flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Complete Payment</h3>
                        <p class="text-xs text-gray-500">Choose how the customer is paying</p>
                    </div>
                    <button @click="showPaymentModal = false" class="p-1 text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Body --}}
                <div class="flex-1 min-h-0 overflow-y-auto px-6 py-5 space-y-4">
                    <div class="bg-gray-50 rounded-xl p-4 space-y-2">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-500">Counter</span>
                            <span class="font-medium text-gray-900" x-text="counterName(selectedCounter)"></span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-500">Table</span>
                            <span class="font-medium text-gray-900" x-text="selectedTable"></span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-500">Items</span>
                            <span class="font-medium text-gray-900" x-text="cartItemCount"></span>
                        </div>
                        <template x-if="orderNotes.trim()">
                            <div class="flex items-start justify-between gap-3 text-sm">
                                <span class="text-gray-500 flex-shrink-0">Note</span>
                                <span class="font-medium text-gray-900 text-right" x-text="orderNotes"></span>
                            </div>
                        </template>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Payment Mode</label>
                            <span class="text-[11px] text-gray-400">Configured in Settings</span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            <template x-for="mode in activePaymentModes" :key="mode.id">
                                <button @click="selectPaymentMode(mode.id)"
                                        :class="selectedPaymentMode === mode.id ? 'border-emerald-500 bg-emerald-50 ring-2 ring-emerald-500/20' : 'border-gray-200 bg-white hover:border-emerald-300 hover:bg-gray-50'"
                                        class="flex flex-col items-center justify-center gap-1.5 px-2 py-3 rounded-xl border text-center transition-all duration-150">
                                    <span class="text-xl" x-text="mode.icon"></span>
                                    <span class="text-xs font-semibold leading-tight" :class="selectedPaymentMode === mode.id ? 'text-emerald-700' : 'text-gray-700'" x-text="mode.name"></span>
                                </button>
                            </template>
                        </div>
                        <div x-show="activePaymentModes.length === 0" class="py-5 px-4 text-center text-sm text-gray-600 bg-gray-50 border border-dashed border-gray-300 rounded-xl">
                            No payment mode is active &mdash; the sale will be saved without one.
                        </div>
                        <p x-show="activePaymentModes.length === 0" x-cloak class="mt-2 text-[11px] text-gray-400 text-center">
                            Enable modes in Settings &rarr; Payment Methods.
                        </p>
                    </div>

                    <template x-if="selectedPaymentModeObj && selectedPaymentModeObj.requiresRef">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                                <span x-text="selectedPaymentModeObj.refLabel || 'Reference'"></span>
                            </label>
                            <input type="text" x-model="paymentRef" @input="paymentError = ''"
                                   :placeholder="'Enter ' + (selectedPaymentModeObj.refLabel || 'reference').toLowerCase()"
                                   class="w-full h-10 px-3.5 text-sm bg-white border rounded-xl outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20">
                        </div>
                    </template>

                    <div x-show="paymentError" x-cloak class="flex items-start gap-2 px-3.5 py-2.5 rounded-xl bg-red-50 border border-red-200 text-xs font-medium text-red-700">
                        <svg class="w-4 h-4 flex-shrink-0 mt-px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                        <span x-text="paymentError"></span>
                    </div>

                    <div class="border-t border-gray-200 pt-4">
                        <div class="flex items-center justify-between">
                            <span class="text-base font-bold text-gray-900">Total Amount</span>
                            <span class="text-2xl font-bold text-gray-900"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(cartTotal)"></span></span>
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="flex-shrink-0 flex flex-col-reverse sm:flex-row gap-2 px-6 py-4 border-t border-gray-200 bg-gray-50">
                    <button @click="showPaymentModal = false; askClearCart();" class="w-full sm:flex-1 py-3 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white transition-colors">Cancel</button>
                    <button @click="confirmPayment(true)"
                            :disabled="savingSale"
                            class="w-full sm:flex-1 inline-flex items-center justify-center gap-2 py-3 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white transition-colors">
                        @heroicon('o-arrow-down-tray', 'w-4 h-4')
                        Save PDF
                    </button>
                    <button @click="confirmPayment()"
                            :disabled="savingSale"
                            class="w-full sm:flex-1 inline-flex items-center justify-center gap-2 py-3 rounded-xl bg-gradient-to-r from-emerald-600 to-emerald-500 text-sm font-semibold text-white hover:from-emerald-700 hover:to-emerald-600 transition-all shadow-sm shadow-emerald-200">
                        @heroicon('o-printer', 'w-4 h-4')
                        <span x-text="activePaymentModes.length === 0 ? 'Save &amp; Print Docket' : 'Pay &amp; Print'"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- PRINT STATUS TOAST --}}
    <template x-teleport="body">
        <div x-show="toast.show"
             x-cloak
             class="fixed z-[95] top-[76px] left-3 right-3 sm:left-auto sm:top-auto sm:bottom-5 sm:right-5 sm:w-96 pointer-events-none"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="flex items-start gap-2.5 rounded-xl px-3.5 py-3 shadow-lg ring-1 bg-white"
                 :class="{
                     'ring-emerald-200': toast.tone === 'success',
                     'ring-red-200': toast.tone === 'error',
                     'ring-blue-200': toast.tone === 'info'
                 }">
                <span class="flex-shrink-0 mt-px" x-show="toast.tone === 'success'">@heroicon('o-check-circle', 'w-5 h-5 text-emerald-600')</span>
                <span class="flex-shrink-0 mt-px" x-show="toast.tone === 'error'">@heroicon('o-exclamation-triangle', 'w-5 h-5 text-red-600')</span>
                <span class="flex-shrink-0 mt-px" x-show="toast.tone === 'info'">@heroicon('o-printer', 'w-5 h-5 text-blue-600')</span>
                <p class="text-sm font-medium leading-snug"
                   :class="{
                       'text-emerald-900': toast.tone === 'success',
                       'text-red-900': toast.tone === 'error',
                       'text-blue-900': toast.tone === 'info'
                   }"
                   x-text="toast.text"></p>
            </div>
        </div>
    </template>

    {{-- STOCK WARNING MODAL --}}
    <template x-teleport="body">
        <div x-show="showStockWarning" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="showStockWarning = false"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md z-10 overflow-hidden" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                <div class="px-6 py-5 border-b border-gray-200 bg-red-50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-red-900">Insufficient Ingredients</h3>
                            <p class="text-sm text-red-600">Cannot fulfill this order. Missing raw materials:</p>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 max-h-64 overflow-y-auto">
                    <div class="space-y-3">
                        <template x-for="(item, idx) in stockWarningItems" :key="idx">
                            <div class="flex items-center justify-between bg-red-50 rounded-xl px-4 py-3">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900" x-text="item.name"></p>
                                    <template x-if="item.itemName"><p class="text-xs text-gray-500" x-text="'For: ' + item.itemName"></p></template>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-red-600">Need: <span class="font-semibold" x-text="item.needed ? item.needed.toFixed(2) + ' ' + item.unit : 'N/A'"></span></p>
                                    <p class="text-xs text-gray-500">Have: <span class="font-semibold" x-text="item.available ? item.available.toFixed(2) + ' ' + item.unit : '0'"></span></p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                    <button @click="showStockWarning = false" class="w-full py-2.5 rounded-xl bg-gray-900 text-sm font-semibold text-white hover:bg-gray-800 transition-colors">Understood</button>
                </div>
            </div>
        </div>
    </template>

    {{-- CLEAR CART CONFIRMATION MODAL --}}
    <template x-teleport="body">
        <div x-show="showClearCartModal"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             @click.away="closeClearCart()"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="closeClearCart()"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm z-10"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
                <div class="px-6 py-5 text-center">
                    <div class="inline-flex items-center justify-center w-14 h-14 bg-red-100 rounded-full mb-4">
                        @heroicon('o-exclamation-triangle', 'w-7 h-7 text-red-600')
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Clear Current Order</h3>
                    <p class="text-sm text-gray-500 mb-1">Are you sure you want to remove all <span class="font-semibold text-gray-900" x-text="cart.length"></span> item(s) from this order?</p>
                    <p class="text-xs text-gray-400 mt-1">This action cannot be undone.</p>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex gap-2">
                    <button @click="closeClearCart()" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white">Cancel</button>
                    <button @click="confirmClearCart()" class="flex-1 py-2.5 rounded-xl bg-red-600 text-sm font-semibold text-white hover:bg-red-700 shadow-sm shadow-red-200">Clear Order</button>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection
