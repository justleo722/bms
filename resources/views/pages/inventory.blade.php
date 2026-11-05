@extends('layouts.master', ['activeNav' => $activeNav ?? 'inventory'])

@section('title', 'Inventory')

@push('head')
<style>
.bms-progress-ring { width: 1rem; height: 1rem; border: 2px solid currentColor; border-right-color: transparent; border-radius: 9999px; animation: bms-progress-spin .7s linear infinite; }
@keyframes bms-progress-spin { to { transform: rotate(360deg); } }
</style>
<script>
window.inventoryData = function (initialQuery) {
    return {
        activeCategory: 'all',
        searchQuery: initialQuery || '',
        counterFilter: 'all',
        dateFilter: 'all',
        showAdjustment: false,
        showBulkAdjustModal: false,
        showImportModal: false,
        showAddProductModal: false,
        showRestockModal: false,
        showTransferModal: false,
        showFilterModal: false,
        showEditModal: false,
        showDeleteModal: false,
        saving: '',
        selectedProduct: null,
        productToDelete: null,
        adjQuantity: 0,
        adjReason: '',
        adjType: 'add',
        bulkAdjLines: {},
        bulkAdjReasons: {},
        bulkAdjSearch: '',
        bulkAdjCategory: 'all',
        bulkAdjCounter: 'all',
        bulkAdjReasonOptions: [
            { id: 'balancing', label: 'Balancing', tone: 'blue', hint: 'Sets stock to the quantity entered' },
            { id: 'bad', label: 'Bad', tone: 'amber', hint: 'Deducts the quantity from current stock' },
            { id: 'expired', label: 'Expired', tone: 'red', hint: 'Deducts the quantity from current stock' },
        ],
        importCategory: @json($inventoryCategories->first()['id'] ?? ''),
        importCounter: @json($inventoryCounters->first()['name'] ?? ''),
        importFile: null,
        importPreview: null,
        importUnknownColumns: [],
        importMissingColumns: [],
        importLoading: false,
        importError: null,
        importSearch: '',
        importFilter: 'all',
        importFileInput: null,
        transferFrom: @json($inventoryCounters->first()['name'] ?? ''),
        transferTo: @json($inventoryCounters->skip(1)->first()['name'] ?? $inventoryCounters->first()['name'] ?? ''),
        transferProducts: [],
        selectedTransferProducts: [],
        transferQuantities: {},
        restockCounter: 'all',
        restockLines: {},
        restockCosts: {},
        restockSells: {},
        restockExpiry: {},
        restockSearch: '',
        restockCategory: 'all',
        restockExpiryFilter: 'all',
        toast: { show: false, text: '', tone: 'info' },
        toastTimer: null,
        productImageLoading: false,
        filterDate: 'all',
        customDateFrom: '',
        customDateTo: '',

        counters: (function(){
            try {
                var raw = localStorage.getItem('bms.counters');
                if (raw) {
                    var parsed = JSON.parse(raw);
                    if (Array.isArray(parsed)) {
                        if (parsed.length && typeof parsed[0] === 'object') return parsed.map(function(c){ return c.name; });
                        return parsed;
                    }
                }
            } catch(e){}
            return ['Main Counter', 'VIP Counter', 'Pool Bar', 'Lounge Bar', 'Bar', 'Kitchen'];
        })(),
        categories: [
            { id: 'all', label: 'All' },
            { id: 'drinks', label: 'Drinks' },
            { id: 'beers', label: 'Beers' },
            { id: 'spirits', label: 'Spirits' },
            { id: 'cocktails', label: 'Cocktails' },
            { id: 'food', label: 'Food' },
            { id: 'sides', label: 'Sides' },
            { id: 'desserts', label: 'Desserts' },
            { id: 'ingredients', label: 'Ingredients' }
        ],
        products: window.BmsTrash.readCollection('bms.inventory.products', [
            { id: 1, name: 'Coca Cola', cat: 'drinks', unit: 'Bottle', stock: 150, reorder: 30, buyPrice: 1000, sellPrice: 1500, counter: 'Main Counter', sup: 'Supplier A' },
            { id: 2, name: 'Fanta Orange', cat: 'drinks', unit: 'Bottle', stock: 120, reorder: 30, buyPrice: 1000, sellPrice: 1500, counter: 'Main Counter', sup: 'Supplier A' },
            { id: 3, name: 'Sprite', cat: 'drinks', unit: 'Bottle', stock: 130, reorder: 30, buyPrice: 1000, sellPrice: 1500, counter: 'Main Counter', sup: 'Supplier A' },
            { id: 4, name: 'Mineral Water', cat: 'drinks', unit: 'Bottle', stock: 200, reorder: 40, buyPrice: 600, sellPrice: 1000, counter: 'Main Counter', sup: 'Supplier A' },
            { id: 5, name: 'Serengeti Lager', cat: 'beers', unit: 'Bottle', stock: 85, reorder: 24, buyPrice: 2000, sellPrice: 3000, counter: 'Main Counter', sup: 'Supplier B' },
            { id: 6, name: 'Kilimanjaro', cat: 'beers', unit: 'Bottle', stock: 90, reorder: 24, buyPrice: 2000, sellPrice: 3000, counter: 'Bar', sup: 'Supplier B' },
            { id: 7, name: 'Castle Lite', cat: 'beers', unit: 'Bottle', stock: 15, reorder: 24, buyPrice: 2500, sellPrice: 3500, counter: 'Pool Bar', sup: 'Supplier B' },
            { id: 8, name: 'Tusker', cat: 'beers', unit: 'Bottle', stock: 60, reorder: 24, buyPrice: 2500, sellPrice: 3500, counter: 'Lounge Bar', sup: 'Supplier B' },
            { id: 9, name: 'Smirnoff Vodka', cat: 'spirits', unit: 'Bottle', stock: 45, reorder: 12, buyPrice: 5500, sellPrice: 8000, counter: 'Main Counter', sup: 'Supplier C' },
            { id: 10, name: 'Captain Morgan', cat: 'spirits', unit: 'Bottle', stock: 40, reorder: 12, buyPrice: 6000, sellPrice: 8500, counter: 'Bar', sup: 'Supplier C' },
            { id: 11, name: 'Johnnie Walker Red', cat: 'spirits', unit: 'Bottle', stock: 35, reorder: 12, buyPrice: 7000, sellPrice: 9500, counter: 'VIP Counter', sup: 'Supplier C' },
            { id: 12, name: 'Mojito Mix', cat: 'cocktails', unit: 'Bottle', stock: 25, reorder: 10, buyPrice: 4500, sellPrice: 6500, counter: 'Bar', sup: 'Supplier D' },
            { id: 13, name: 'Chicken Wings', cat: 'food', unit: 'Portion', stock: 0, reorder: 20, buyPrice: 5500, sellPrice: 8500, counter: 'Kitchen', sup: 'Supplier E' },
            { id: 14, name: 'Beef Burger', cat: 'food', unit: 'Portion', stock: 0, reorder: 20, buyPrice: 5500, sellPrice: 8500, counter: 'Kitchen', sup: 'Supplier E' },
            { id: 15, name: 'Grilled Chicken', cat: 'food', unit: 'Portion', stock: 0, reorder: 15, buyPrice: 8000, sellPrice: 12000, counter: 'Kitchen', sup: 'Supplier E' },
            { id: 16, name: 'Fish & Chips', cat: 'food', unit: 'Portion', stock: 0, reorder: 15, buyPrice: 7000, sellPrice: 11000, counter: 'Kitchen', sup: 'Supplier E' },
            { id: 17, name: 'French Fries', cat: 'sides', unit: 'Portion', stock: 0, reorder: 30, buyPrice: 1500, sellPrice: 2500, counter: 'Kitchen', sup: 'Supplier E' },
            { id: 18, name: 'Onion Rings', cat: 'sides', unit: 'Portion', stock: 0, reorder: 20, buyPrice: 2000, sellPrice: 3000, counter: 'Kitchen', sup: 'Supplier E' },
            { id: 19, name: 'Coleslaw', cat: 'sides', unit: 'Portion', stock: 0, reorder: 20, buyPrice: 1000, sellPrice: 2000, counter: 'Kitchen', sup: 'Supplier E' },
            { id: 20, name: 'Chocolate Cake', cat: 'desserts', unit: 'Slice', stock: 12, reorder: 10, buyPrice: 3000, sellPrice: 4500, counter: 'Main Counter', sup: 'Supplier F' },
            { id: 21, name: 'Ice Cream', cat: 'desserts', unit: 'Cup', stock: 24, reorder: 12, buyPrice: 2000, sellPrice: 3500, counter: 'Main Counter', sup: 'Supplier F' },
            { id: 22, name: 'Fruit Salad', cat: 'desserts', unit: 'Bowl', stock: 0, reorder: 10, buyPrice: 2500, sellPrice: 4000, counter: 'Kitchen', sup: 'Supplier F' },
            { id: 23, name: 'Chicken Breast', cat: 'ingredients', unit: 'Kg', stock: 15, reorder: 10, buyPrice: 8000, sellPrice: 0, counter: 'Kitchen', sup: 'Supplier C' },
            { id: 24, name: 'Beef', cat: 'ingredients', unit: 'Kg', stock: 12, reorder: 8, buyPrice: 12000, sellPrice: 0, counter: 'Kitchen', sup: 'Supplier C' },
            { id: 25, name: 'Potatoes', cat: 'ingredients', unit: 'Kg', stock: 40, reorder: 20, buyPrice: 1200, sellPrice: 0, counter: 'Kitchen', sup: 'Supplier G' },
            { id: 26, name: 'Cooking Oil', cat: 'ingredients', unit: 'L', stock: 25, reorder: 10, buyPrice: 3500, sellPrice: 0, counter: 'Kitchen', sup: 'Supplier G' },
            { id: 27, name: 'Tomatoes', cat: 'ingredients', unit: 'Kg', stock: 18, reorder: 10, buyPrice: 2000, sellPrice: 0, counter: 'Kitchen', sup: 'Supplier G' },
            { id: 28, name: 'Onions', cat: 'ingredients', unit: 'Kg', stock: 22, reorder: 10, buyPrice: 1800, sellPrice: 0, counter: 'Kitchen', sup: 'Supplier G' }
        ]),

        movements: [
            { id: 1, product: 'Serengeti Lager', counter: 'Main Counter', type: 'purchased', qty: 48, date: '2026-10-24 09:15', user: 'John M.' },
            { id: 2, product: 'Smirnoff Vodka', counter: 'Main Counter', type: 'used', qty: -2, date: '2026-10-24 10:30', user: 'POS' },
            { id: 3, product: 'Coca Cola', counter: 'Main Counter', type: 'purchased', qty: 100, date: '2026-10-24 11:00', user: 'Peter O.' },
            { id: 4, product: 'Castle Lite', counter: 'Pool Bar', type: 'used', qty: -3, date: '2026-10-24 12:00', user: 'POS' },
            { id: 5, product: 'Beef', counter: 'Kitchen', type: 'purchased', qty: 20, date: '2026-10-24 13:20', user: 'John M.' },
            { id: 6, product: 'Chicken Breast', counter: 'Kitchen', type: 'used', qty: -5, date: '2026-10-24 14:45', user: 'Kitchen' },
            { id: 7, product: 'Johnnie Walker Red', counter: 'VIP Counter', type: 'used', qty: -2, date: '2026-10-24 16:48', user: 'POS' },
            { id: 8, product: 'Cooking Oil', counter: 'Kitchen', type: 'adjusted', qty: -2, date: '2026-10-24 17:15', user: 'David Msuya' }
        ],

        units: window.BmsUnits.merge([
            window.BmsUnits.read(),
            @json($units ?? []),
            ['Units']
        ]),

        init() {
            var self = this;
            this.products = @json($inventoryProducts);
            this.categories = [{ id: 'all', label: 'All', icon: '' }, ...@json($inventoryCategories)];
            this.counters = @json($inventoryCounters->pluck('name'));
            this.movements = @json($inventoryMovements);
            this.units = @json($inventoryUnits);
            this.newProduct.cat = this.categories.find(function (category) { return category.id !== 'all'; })?.id || '';
            this.newProduct.unit = this.units[0] || 'Units';
            this.newProduct.counter = this.counters[0] || '';
            this.products.forEach(function (product) {
                if (product.unit && self.units.indexOf(product.unit) === -1) self.units.push(product.unit);
            });
        },
        newProduct: {
            name: '',
            cat: 'spirits',
            unit: 'Units',
            stock: 0,
            reorder: 10,
            buyPrice: 0,
            sellPrice: 0,
            counter: 'Main Counter',
            expiryDate: '',
            sup: '',
            image: ''
        },
        newProductErrors: {},
        csrfToken() {
            var meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        },
        apiError(err, fallback) {
            var data = err.response && err.response.data;
            if (data && data.errors) return Object.keys(data.errors).map(function (key) { return data.errors[key][0]; }).join(' ');
            return data && data.message ? data.message : fallback;
        },
        categoryDatabaseId(slug) {
            var category = this.categories.find(function (item) { return item.id === slug; });
            return category ? category.databaseId : null;
        },
        counterDatabaseId(name) {
            var counters = @json($inventoryCounters);
            var counter = counters.find(function (item) { return item.name === name; });
            return counter ? counter.id : null;
        },
        applyStock(stock) {
            var index = this.products.findIndex(function (product) { return product.id === stock.id; });
            if (index === -1) this.products.push(stock);
            else this.products.splice(index, 1, stock);
        },
        stockUrl(id) { return @json(url('/inventory/stocks')) + '/' + id; },
        productUrl(id) { return @json(url('/inventory/products')) + '/' + id; },

        get filteredProducts() {
            var q = this.searchQuery.toLowerCase().trim();
            var c = this.counterFilter;
            return this.products.filter(function (p) {
                if (this.activeCategory !== 'all' && p.cat !== this.activeCategory) return false;
                if (c !== 'all' && p.counter !== c) return false;
                if (!this.matchesDateFilter(p)) return false;
                if (q) {
                    var haystack = [p.name, p.sup, p.cat, p.counter, p.unit]
                        .map(function (v) { return String(v || '').toLowerCase(); })
                        .join(' ');
                    if (!haystack.includes(q)) return false;
                }
                return true;
            }.bind(this));
        },

        matchesDateFilter(product) {
            if (this.filterDate === 'all') return true;
            if (!product.dateAdded) return false;

            var toDate = function (value) {
                var parts = value.split('-').map(Number);
                return new Date(parts[0], parts[1] - 1, parts[2]);
            };
            var formatDate = function (date) {
                return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
            };
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            var todayString = formatDate(today);
            var date = product.dateAdded;
            var start;
            var end;

            if (this.filterDate === 'custom') {
                start = this.customDateFrom || null;
                end = this.customDateTo || null;
                if (start && end && start > end) return false;
                return (!start || date >= start) && (!end || date <= end);
            }

            if (this.filterDate === 'today') return date === todayString;
            if (this.filterDate === 'yesterday') {
                today.setDate(today.getDate() - 1);
                return date === formatDate(today);
            }

            var weekStart = new Date(today);
            weekStart.setDate(today.getDate() - ((today.getDay() + 6) % 7));
            if (this.filterDate === 'thisWeek') {
                start = formatDate(weekStart);
                end = formatDate(today);
            } else if (this.filterDate === 'lastWeek') {
                end = new Date(weekStart);
                end.setDate(end.getDate() - 1);
                start = new Date(end);
                start.setDate(start.getDate() - 6);
                start = formatDate(start);
                end = formatDate(end);
            } else if (this.filterDate === 'thisMonth') {
                start = formatDate(new Date(today.getFullYear(), today.getMonth(), 1));
                end = formatDate(today);
            } else if (this.filterDate === 'lastMonth') {
                start = formatDate(new Date(today.getFullYear(), today.getMonth() - 1, 1));
                end = formatDate(new Date(today.getFullYear(), today.getMonth(), 0));
            } else if (this.filterDate === 'lastThreeMonths') {
                start = formatDate(new Date(today.getFullYear(), today.getMonth() - 2, today.getDate()));
                end = formatDate(today);
            } else if (this.filterDate === 'thisYear') {
                start = formatDate(new Date(today.getFullYear(), 0, 1));
                end = formatDate(today);
            } else {
                return true;
            }

            return date >= start && date <= end;
        },

        get totalProducts() { return this.filteredProducts.length; },
        get lowStockCount() { return this.filteredProducts.filter(function (p) { return p.stock > 0 && p.stock < p.reorder; }).length; },
        get outOfStockCount() { return this.filteredProducts.filter(function (p) { return p.stock === 0; }).length; },
        get inventoryValue() {
            return this.filteredProducts.reduce(function (sum, p) { return sum + (p.stock * p.buyPrice); }, 0);
        },
        get profitEstimate() {
            return this.filteredProducts.reduce(function (sum, p) { return sum + (p.stock * (p.sellPrice - p.buyPrice)); }, 0);
        },

        // ===== Restock state =====
        get restockList() {
            var q = this.restockSearch.toLowerCase().trim();
            var c = this.restockCounter;
            var self = this;
            return this.products.filter(function (p) {
                if (self.restockCategory !== 'all' && p.cat !== self.restockCategory) return false;
                if (c !== 'all' && p.counter !== c) return false;
                if (q) {
                    var haystack = [p.name, p.sup, p.cat, p.counter, p.unit]
                        .map(function (v) { return String(v || '').toLowerCase(); })
                        .join(' ');
                    if (!haystack.includes(q)) return false;
                }
                if (self.restockExpiryFilter !== 'all') {
                    var bucket = self.productExpiry(p) ? self.expiryStatus(p).key : 'none';
                    if (self.restockExpiryFilter === 'none' && bucket !== 'none') return false;
                    if (self.restockExpiryFilter === 'expired' && bucket !== 'expired') return false;
                    if (self.restockExpiryFilter === 'soon' && bucket !== 'soon') return false;
                }
                return true;
            });
        },
        restockQty(id) { return parseFloat(this.restockLines[id]) || 0; },
        restockBuy(p) {
            var v = this.restockCosts[p.id];
            if (v === undefined || v === null || v === '') return parseFloat(p.buyPrice) || 0;
            return parseFloat(v) || 0;
        },
        restockCostChanged(p) {
            return this.restockQty(p.id) > 0 && this.restockBuy(p) !== (parseFloat(p.buyPrice) || 0);
        },
        restockSell(p) {
            var v = this.restockSells[p.id];
            if (v === undefined || v === null || v === '') return parseFloat(p.sellPrice) || 0;
            return parseFloat(v) || 0;
        },
        restockSellChanged(p) {
            return this.restockQty(p.id) > 0 && this.restockSell(p) !== (parseFloat(p.sellPrice) || 0);
        },
        restockHasExpiry(p) {
            return this.restockQty(p.id) > 0 && this.restockExpiry[p.id] && this.restockExpiry[p.id].trim();
        },
        restockNewQty(p) { return (parseFloat(p.stock) || 0) + this.restockQty(p.id); },
        restockLineValue(p) { return this.restockQty(p.id) * this.restockBuy(p); },
        get restockLineCount() {
            var self = this;
            return this.products.filter(function (p) { return self.restockQty(p.id) > 0; }).length;
        },
        get restockTotalUnits() {
            var self = this;
            return this.products.reduce(function (sum, p) { return sum + self.restockQty(p.id); }, 0);
        },
        get restockTotalValue() {
            var self = this;
            return this.products.reduce(function (sum, p) { return sum + (self.restockQty(p.id) > 0 ? self.restockLineValue(p) : 0); }, 0);
        },

        isLowStock(p) { return p.stock > 0 && p.stock < p.reorder; },
        isOutOfStock(p) { return p.stock === 0; },

        // ---- Expiry ----
        // `expiry` is the historic field name used by the restock flow, so it
        // is read as a fallback alongside `expiryDate`.
        productExpiry(p) {
            return (p && (p.expiryDate || p.expiry)) || '';
        },

        expiryStatus(p) {
            var value = this.productExpiry(p);
            if (!value) return { label: 'No expiry', tone: 'gray', key: 'none', days: null };

            var parsed = new Date(value + 'T00:00:00');
            if (isNaN(parsed.getTime())) return { label: '—', tone: 'gray', key: 'none', days: null };

            var startOfToday = new Date();
            startOfToday.setHours(0, 0, 0, 0);
            var days = Math.round((parsed - startOfToday) / 86400000);

            if (days < 0) return { label: 'Expired ' + Math.abs(days) + 'd ago', tone: 'red', key: 'expired', days: days };
            if (days === 0) return { label: 'Expires today', tone: 'red', key: 'soon', days: 0 };
            if (days <= 30) return { label: days + 'd left', tone: 'amber', key: 'soon', days: days };
            return { label: days + 'd left', tone: 'green', key: 'ok', days: days };
        },

        expiryToneClasses(tone) {
            return {
                red: 'bg-red-100 text-red-700',
                amber: 'bg-amber-100 text-amber-700',
                green: 'bg-emerald-100 text-emerald-700',
                gray: 'bg-gray-100 text-gray-500',
            }[tone] || 'bg-gray-100 text-gray-500';
        },

        formatExpiry(p) {
            var value = this.productExpiry(p);
            if (!value) return '—';
            var d = new Date(value + 'T00:00:00');
            if (isNaN(d.getTime())) return value;
            return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        },

        // ---- Add Product ----
        openAddProductModal() {
            this.newProduct = {
                name: '', cat: this.categories.find(function (category) { return category.id !== 'all'; })?.id || '', unit: this.units[0] || 'Units', stock: 0,
                reorder: 10, buyPrice: 0, sellPrice: 0,
                counter: this.counters[0] || '', expiryDate: '', sup: '', image: '',
            };
            this.newProductErrors = {};
            this.showAddProductModal = true;
        },

        validateNewProduct() {
            var p = this.newProduct;
            var errors = {};

            if (!String(p.name || '').trim()) {
                errors.name = 'Enter a product name.';
            }

            if (p.buyPrice === '' || p.buyPrice === null || isNaN(parseFloat(p.buyPrice)) || parseFloat(p.buyPrice) < 0) {
                errors.buyPrice = 'Enter a buying price of 0 or more.';
            }

            if (p.sellPrice === '' || p.sellPrice === null || isNaN(parseFloat(p.sellPrice)) || parseFloat(p.sellPrice) < 0) {
                errors.sellPrice = 'Enter a selling price of 0 or more.';
            } else if (!errors.buyPrice && parseFloat(p.sellPrice) < parseFloat(p.buyPrice)) {
                errors.sellPrice = 'Selling price cannot be lower than the buying price.';
            }

            if (p.stock === '' || p.stock === null || isNaN(parseFloat(p.stock)) || parseFloat(p.stock) < 0) {
                errors.stock = 'Enter a stock quantity of 0 or more.';
            }

            if (p.reorder !== '' && p.reorder !== null && (isNaN(parseFloat(p.reorder)) || parseFloat(p.reorder) < 0)) {
                errors.reorder = 'Enter 0 or more, or leave blank.';
            }

            // Expiry is optional, but if given it must be a real date.
            if (p.expiryDate) {
                var parsed = new Date(p.expiryDate + 'T00:00:00');
                if (isNaN(parsed.getTime())) {
                    errors.expiryDate = 'That is not a valid date.';
                } else {
                    var iso = parsed.getFullYear() + '-' +
                        String(parsed.getMonth() + 1).padStart(2, '0') + '-' +
                        String(parsed.getDate()).padStart(2, '0');
                    if (iso !== p.expiryDate) {
                        errors.expiryDate = 'That date does not exist. Check the day and month.';
                    }
                }
            }

            this.newProductErrors = errors;

            return Object.keys(errors).length === 0;
        },

        saveNewProduct() {
            if (this.productImageLoading) { this.flash('Wait for the product image to finish loading.', 'error'); return; }
            if (!this.validateNewProduct()) {
                this.flash('Fix the highlighted fields before saving.', 'error');
                return;
            }
            var self = this;
            var p = this.newProduct;
            if (this.saving) return;
            this.saving = 'product';
            window.axios.post(@json(route('inventory.products.store')), {
                name: String(p.name).trim(),
                category_id: this.categoryDatabaseId(p.cat),
                unit: p.unit,
                counter_id: this.counterDatabaseId(p.counter),
                quantity: p.stock,
                reorder_level: p.reorder || 0,
                buy_price: p.buyPrice,
                sell_price: p.sellPrice,
                expiry_date: p.expiryDate || null,
                supplier: p.sup || null,
                image: p.image || null,
            }, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function (res) {
                    self.applyStock(res.data.product);
                    self.showAddProductModal = false;
                    self.flash(res.data.message, 'success');
                })
                .catch(function (err) { self.flash(self.apiError(err, 'Could not add the product.'), 'error'); })
                .then(function () { self.saving = ''; });
        },

        stockBarWidth(p) {
            if (p.stock === 0) return 0;
            var ratio = p.stock / p.reorder;
            return Math.min(ratio * 100, 100);
        },

        stockBarColor(p) {
            if (p.stock === 0) return 'bg-red-500';
            if (p.stock < p.reorder) return 'bg-amber-500';
            return 'bg-emerald-500';
        },

        formatMoney(v) { return (v || 0).toLocaleString(); },
        formatNumber(v) { return (v || 0).toLocaleString(); },

        setCategory(cat) { this.activeCategory = cat; },

        openAdjustment(product) {
            this.selectedProduct = Object.assign({}, product);
            this.adjQuantity = 0;
            this.adjReason = '';
            this.adjType = 'add';
            this.showAdjustment = true;
        },
        applyAdjustment() {
            var self = this;
            if (!this.selectedProduct || this.saving) return;
            if (!this.adjReason) { this.flash('Choose a reason for this adjustment.', 'error'); return; }
            this.saving = 'adjustment';
            window.axios.post(this.stockUrl(this.selectedProduct.id) + '/adjust', {
                mode: this.adjType,
                quantity: this.adjQuantity,
                reason: this.adjReason,
            }, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function (res) {
                    self.applyStock(res.data.product);
                    self.showAdjustment = false;
                    self.flash(res.data.message, 'success');
                })
                .catch(function (err) { self.flash(self.apiError(err, 'Could not adjust stock.'), 'error'); })
                .then(function () { self.saving = ''; });
        },

        // ===== Bulk adjust =====
        get bulkAdjList() {
            var q = this.bulkAdjSearch.toLowerCase().trim();
            var self = this;
            return this.products.filter(function (p) {
                if (self.bulkAdjCategory !== 'all' && p.cat !== self.bulkAdjCategory) return false;
                if (self.bulkAdjCounter !== 'all' && p.counter !== self.bulkAdjCounter) return false;
                if (q && p.name.toLowerCase().indexOf(q) === -1 && p.sup.toLowerCase().indexOf(q) === -1) return false;
                return true;
            });
        },
        get bulkAdjCategories() {
            var self = this;
            return this.categories.filter(function (c) { return c.id !== 'all'; });
        },
        bulkAdjReasonFor(id) {
            var rid = this.bulkAdjReasons[id] || 'balancing';
            return this.bulkAdjReasonOptions.find(function (r) { return r.id === rid; }) || this.bulkAdjReasonOptions[0];
        },
        bulkAdjQty(id) { return parseFloat(this.bulkAdjLines[id]) || 0; },
        bulkAdjIsBalance(id) { return this.bulkAdjReasonFor(id).id === 'balancing'; },
        bulkAdjNewQty(p) {
            var q = this.bulkAdjQty(p.id);
            var next = this.bulkAdjIsBalance(p.id) ? q : (parseFloat(p.stock) || 0) - q;
            return Math.round(Math.max(next, 0) * 10000) / 10000;
        },
        bulkAdjOverflow(p) {
            return !this.bulkAdjIsBalance(p.id) && this.bulkAdjQty(p.id) > (parseFloat(p.stock) || 0);
        },
        get bulkAdjLineCount() {
            var self = this;
            return this.products.filter(function (p) { return self.bulkAdjQty(p.id) > 0; }).length;
        },
        get bulkAdjHasOverflow() {
            var self = this;
            return this.products.some(function (p) { return self.bulkAdjQty(p.id) > 0 && self.bulkAdjOverflow(p); });
        },
        get bulkAdjTotalQty() {
            var self = this;
            return this.products.reduce(function (sum, p) { return sum + self.bulkAdjQty(p.id); }, 0);
        },
        get bulkAdjValueImpact() {
            var self = this;
            return this.products.reduce(function (sum, p) {
                if (self.bulkAdjQty(p.id) <= 0) return sum;
                return sum + ((self.bulkAdjNewQty(p) - (parseFloat(p.stock) || 0)) * (parseFloat(p.buyPrice) || 0));
            }, 0);
        },
        get bulkAdjReasonSummary() {
            var self = this;
            var used = {};
            this.products.forEach(function (p) {
                if (self.bulkAdjQty(p.id) <= 0) return;
                var r = self.bulkAdjReasonFor(p.id);
                used[r.id] = (used[r.id] || 0) + 1;
            });
            return this.bulkAdjReasonOptions
                .filter(function (r) { return used[r.id]; })
                .map(function (r) { return { id: r.id, label: r.label, tone: r.tone, count: used[r.id] }; });
        },
        adjToneClass(tone) {
            var map = {
                blue: 'bg-blue-50 text-blue-700 ring-blue-600/20 border-blue-200',
                amber: 'bg-amber-50 text-amber-700 ring-amber-600/20 border-amber-200',
                red: 'bg-red-50 text-red-700 ring-red-600/20 border-red-200',
            };
            return map[tone] || map.blue;
        },
        adjCategoryIcon(cat) {
            var found = this.categories.find(function (c) { return c.id === cat; });
            return found ? found.icon : '📦';
        },
        openBulkAdjust() {
            this.bulkAdjLines = {};
            this.bulkAdjReasons = {};
            this.bulkAdjSearch = '';
            this.bulkAdjCategory = 'all';
            this.bulkAdjCounter = 'all';
            this.showBulkAdjustModal = true;
        },
        closeBulkAdjustModal() {
            this.showBulkAdjustModal = false;
            this.bulkAdjLines = {};
            this.bulkAdjReasons = {};
        },
        saveBulkAdjust() {
            if (this.bulkAdjLineCount === 0) { this.flash('Enter a quantity for at least one product.', 'error'); return; }
            if (this.bulkAdjHasOverflow) { this.flash('Adjustment cannot be greater than the current stock.', 'error'); return; }
            if (this.saving) return;
            var self = this;
            var items = this.products.filter(function (p) { return self.bulkAdjQty(p.id) > 0; }).map(function (p) {
                var reason = self.bulkAdjReasonFor(p.id);
                return {
                    stock_id: p.id,
                    mode: reason.id === 'balancing' ? 'set' : 'remove',
                    quantity: reason.id === 'balancing' ? self.bulkAdjQty(p.id) : self.bulkAdjQty(p.id),
                    reason: reason.label,
                };
            });
            this.saving = 'bulk-adjustment';
            window.axios.post(@json(route('inventory.adjustments.store')), { items: items }, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function (res) {
                    (res.data.products || []).forEach(function (stock) { self.applyStock(stock); });
                    self.closeBulkAdjustModal();
                    self.flash(res.data.message, 'success');
                })
                .catch(function (err) { self.flash(self.apiError(err, 'Could not save stock adjustments.'), 'error'); })
                .then(function () { self.saving = ''; });
        },

        openRestock() {
            this.restockCounter = 'all';
            this.restockLines = {};
            this.restockCosts = {};
            this.restockSells = {};
            this.products.forEach(function (product) {
                this.restockCosts[product.id] = product.buyPrice;
                this.restockSells[product.id] = product.sellPrice;
            }, this);
            this.restockExpiry = {};
            this.restockSearch = '';
            this.restockCategory = 'all';
            this.showRestockModal = true;
        },

        closeRestockModal() {
            this.showRestockModal = false;
            this.restockLines = {};
            this.restockCosts = {};
            this.restockSells = {};
            this.restockExpiry = {};
        },

        saveRestock() {
            var self = this;
            if (this.restockLineCount === 0) { this.flash('Enter a quantity for at least one product.', 'error'); return; }
            if (this.saving) return;
            var items = this.products.filter(function (product) { return self.restockQty(product.id) > 0; }).map(function (product) {
                var item = { stock_id: product.id, quantity: self.restockQty(product.id) };
                if (self.restockCostChanged(product)) item.buy_price = self.restockBuy(product);
                if (self.restockSellChanged(product)) item.sell_price = self.restockSell(product);
                if (self.restockExpiry[product.id]) item.expiry_date = self.restockExpiry[product.id];
                return item;
            });
            this.saving = 'restock';
            window.axios.post(@json(route('inventory.restock')), { items: items }, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function (res) {
                    (res.data.products || []).forEach(function (stock) { self.applyStock(stock); });
                    self.flash(res.data.message, 'success');
                    self.closeRestockModal();
                })
                .catch(function (err) { self.flash(self.apiError(err, 'Could not restock products.'), 'error'); })
                .then(function () { self.saving = ''; });
        },

        flash(text, tone) {
            var self = this;
            this.toast = { show: true, text: text, tone: tone || 'info' };
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(function () { self.toast.show = false; }, 4000);
        },

        openTransfer() {
            this.transferFrom = this.counters[0] || '';
            this.transferTo = this.counters.find(function (counter) { return counter !== this.transferFrom; }.bind(this)) || '';
            this.transferProducts = [];
            this.selectedTransferProducts = [];
            this.transferQuantities = {};
            this.loadTransferProducts();
            this.showTransferModal = true;
        },

        loadTransferProducts() {
            var from = this.transferFrom;
            this.transferProducts = this.products.filter(function (p) {
                return p.counter === from && p.stock > 0;
            });
            this.selectedTransferProducts = [];
            this.transferQuantities = {};
        },

        openEdit(product) {
            this.selectedProduct = Object.assign({}, product);
            this.showEditModal = true;
        },
        handleProductImageUpload(event, mode) {
            var file = event.target.files && event.target.files[0];
            if (!file) return;
            if (!file.type.startsWith('image/')) {
                this.flash('Choose a valid image file.', 'error');
                event.target.value = '';
                return;
            }
            if (file.size > 2 * 1024 * 1024) {
                this.flash('Product images must be 2 MB or smaller.', 'error');
                event.target.value = '';
                return;
            }

            var self = this;
            this.productImageLoading = true;
            var reader = new FileReader();
            reader.onload = function (result) {
                if (typeof result.target.result === 'string') {
                    if (mode === 'new') self.newProduct.image = result.target.result;
                    else if (self.selectedProduct) self.selectedProduct.image = result.target.result;
                }
                self.productImageLoading = false;
            };
            reader.onerror = function () {
                self.productImageLoading = false;
                self.flash('Could not read the selected image.', 'error');
            };
            reader.readAsDataURL(file);
        },
        saveEdit() {
            var self = this;
            var p = this.selectedProduct;
            if (!p || this.saving) return;
            if (this.productImageLoading) { this.flash('Wait for the product image to finish loading.', 'error'); return; }
            this.saving = 'edit';
            window.axios.put(this.productUrl(p.productId), {
                name: p.name,
                sku: p.sku || null,
                category_id: this.categoryDatabaseId(p.cat),
                unit: p.unit,
                buy_price: p.buyPrice,
                sell_price: p.sellPrice,
                supplier: p.sup || null,
                stock_id: p.id,
                reorder_level: p.reorder,
                image: p.image || null,
            }, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function (res) {
                    self.applyStock(res.data.product);
                    self.showEditModal = false;
                    self.flash(res.data.message, 'success');
                })
                .catch(function (err) { self.flash(self.apiError(err, 'Could not update the product.'), 'error'); })
                .then(function () { self.saving = ''; });
        },

        openDelete(product) {
            this.productToDelete = product;
            this.showDeleteModal = true;
        },
        saveTransfer() {
            if (!this.selectedTransferProducts.length) { this.flash('Select at least one product to transfer.', 'error'); return; }
            if (!this.transferTo || this.transferTo === this.transferFrom) { this.flash('Choose a different destination counter.', 'error'); return; }
            var self = this;
            var items = this.selectedTransferProducts.map(function (id) {
                return { stock_id: id, quantity: parseFloat(self.transferQuantities[id]) || 0 };
            }).filter(function (item) { return item.quantity > 0; });
            if (!items.length) { this.flash('Enter a transfer quantity for at least one selected product.', 'error'); return; }
            this.saving = 'transfer';
            window.axios.post(@json(route('inventory.transfer')), {
                to_counter_id: this.counterDatabaseId(this.transferTo),
                items: items,
            }, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function (res) {
                    self.showTransferModal = false;
                    self.flash(res.data.message, 'success');
                    window.location.reload();
                })
                .catch(function (err) { self.flash(self.apiError(err, 'Could not transfer stock.'), 'error'); })
                .then(function () { self.saving = ''; });
        },

        confirmDelete() {
            var self = this;
            if (!this.productToDelete || this.saving) return;
            var product = this.productToDelete;
            this.saving = 'delete';
            window.axios.delete(this.productUrl(product.productId), { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function (res) {
                    self.products = self.products.filter(function (item) { return item.productId !== product.productId; });
                    self.flash(res.data.message, 'success');
                    self.showDeleteModal = false;
                    self.productToDelete = null;
                })
                .catch(function (err) { self.flash(self.apiError(err, 'Could not delete the product.'), 'error'); })
                .then(function () { self.saving = ''; });
        },

        movementIcon(type) {
            var m = { opening: '🟢', restock: '🟢', adjustment: '🔵', transfer_in: '🟠', transfer_out: '🟠' };
            return m[type] || '⚪';
        },

        // Applied-to-every-row values. Resolved server-side by
        // ImportSchema::assignmentDefaults() so nothing here has to call a
        // method that is defined further down the object literal.
        importAssignments: @json($importAssignments ?? []),
        importDefaults: @json($importDefaults ?? []),
        importCategory: @json($inventoryCategories->first()['id'] ?? ''),
        importCounter: @json($inventoryCounters->first()['name'] ?? ''),
        importUnitDefault: @json($importApplyDefaults['unit'] ?? 'Bottle'),

        get importValidRows() {
            if (!this.importPreview) return [];
            return this.importPreview.filter(function (r) { return r._valid; });
        },

        get importInvalidRows() {
            if (!this.importPreview) return [];
            return this.importPreview.filter(function (r) { return !r._valid; });
        },

        get importVisibleRows() {
            if (!this.importPreview) return [];
            var self = this;
            var q = this.importSearch.toLowerCase().trim();
            return this.importPreview.filter(function (r) {
                if (self.importFilter === 'valid' && !r._valid) return false;
                if (self.importFilter === 'invalid' && r._valid) return false;
                if (q && String(r.name || '').toLowerCase().indexOf(q) === -1
                       && String(r.sup || '').toLowerCase().indexOf(q) === -1) return false;
                return true;
            });
        },

        get importTotalValue() {
            return this.importValidRows.reduce(function (sum, r) {
                return sum + ((parseFloat(r.buyPrice) || 0) * (parseFloat(r.stock) || 0));
            }, 0);
        },

        openImport() {
            this.resetImport();
            this.showImportModal = true;
        },

        resetImport() {
            this.importFile = null;
            this.importPreview = null;
            this.importUnknownColumns = [];
            this.importMissingColumns = [];
            this.importError = null;
            this.importLoading = false;
            this.importSearch = '';
            this.importFilter = 'all';
            if (this.importFileInput) { this.importFileInput.value = ''; }
            this.importCategory = this.categories.find(function (category) { return category.id !== 'all'; })?.id || '';
            this.importCounter = this.counters[0] || '';
        },

        handleImportFile(event) {
            var file = event.target.files && event.target.files[0];
            this.importFileInput = event.target;
            if (!file) return;

            var self = this;
            this.importFile = file;
            this.importPreview = null;
            this.importError = null;
            this.importLoading = true;
            this.importSearch = '';
            this.importFilter = 'all';

            var body = new FormData();
            body.append('file', file);
            body.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

            fetch('{{ route('inventory.products.import.preview') }}', {
                method: 'POST',
                body: body,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (res) { return res.json().catch(function () { return {}; }); })
            .then(function (data) {
                self.importLoading = false;
                if (!data.ok) {
                    var message = data.error
                        || (data.errors && data.errors.file && data.errors.file[0])
                        || 'That file could not be read.';
                    self.importError = message;
                    return;
                }
                if (data.summary.total === 0) {
                    self.importError = 'No product rows were found under the header row.';
                    return;
                }
                self.importPreview = data.products;
                self.importUnknownColumns = data.unknown_columns || [];
                self.importMissingColumns = data.missing_columns || [];
            })
            .catch(function () {
                self.importLoading = false;
                self.importError = 'Something went wrong while reading the file. Please try again.';
            });
        },

        confirmImport() {
            var rows = this.importValidRows;
            if (rows.length === 0) { this.flash('There are no valid rows to import.', 'error'); return; }
            if (!this.importFile || this.saving) return;
            var self = this;
            var body = new FormData();
            body.append('file', this.importFile);
            body.append('category_id', this.categoryDatabaseId(this.importCategory));
            body.append('counter_id', this.counterDatabaseId(this.importCounter));
            body.append('_token', this.csrfToken());
            this.saving = 'import';
            window.axios.post(@json(route('inventory.products.import')), body, {
                headers: { 'X-CSRF-TOKEN': self.csrfToken(), 'Content-Type': 'multipart/form-data' },
            })
                .then(function (res) {
                    self.showImportModal = false;
                    self.resetImport();
                    self.flash(res.data.message, 'success');
                    window.location.reload();
                })
                .catch(function (err) { self.flash(self.apiError(err, 'Could not import products.'), 'error'); })
                .then(function () { self.saving = ''; });
        },

        // Note: filter() returns an array, and [] is truthy, so the length has
        // to be checked. Testing the array itself silently yielded undefined.
        categoryIdFor(label) {
            var found = this.categories.filter(function (c) {
                return c.label === label;
            });
            return found.length ? found[0].id : null;
        },

        categoryLabelFor(id) {
            var found = this.categories.filter(function (c) {
                return c.id === id;
            });
            return found.length ? found[0].label : id;
        },
    };
};
</script>
<style>
.inv-scroll::-webkit-scrollbar { width: 4px; height: 4px; }
.inv-scroll::-webkit-scrollbar-track { background: transparent; }
.inv-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
</style>
@endpush

@section('content')
<div x-data="inventoryData(@js(is_string(request('q')) ? request('q') : ''))" class="pb-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Inventory</h1>
            <p class="mt-1 text-sm text-gray-500">Manage stock across all counters, track levels, and handle transfers.</p>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-gray-500">Total Products</p>
                <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">@heroicon('o-cube', 'w-5 h-5 text-blue-600')</div>
            </div>
            <p class="mt-2 text-2xl font-bold text-gray-900" x-text="totalProducts"></p>
            <p class="text-xs text-gray-500 mt-1">items</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-gray-500">Low Stock</p>
                <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center">@heroicon('o-exclamation-triangle', 'w-5 h-5 text-amber-600')</div>
            </div>
            <p class="mt-2 text-2xl font-bold text-gray-900" x-text="lowStockCount"></p>
            <p class="text-xs text-gray-500 mt-1">need reorder</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-gray-500">Out of Stock</p>
                <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center">@heroicon('o-x-circle', 'w-5 h-5 text-red-600')</div>
            </div>
            <p class="mt-2 text-2xl font-bold text-gray-900" x-text="outOfStockCount"></p>
            <p class="text-xs text-gray-500 mt-1">items</p>
        </div>
        @if (auth()->user()?->hasPermission('inventory', 'value'))
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-gray-500">Inventory Value</p>
                <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">@heroicon('o-banknotes', 'w-5 h-5 text-emerald-600')</div>
            </div>
            <p class="mt-2 text-2xl font-bold text-gray-900"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(inventoryValue)"></span></p>
            <p class="text-xs text-gray-500 mt-1">total value</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-gray-500">Profit Estimate</p>
                <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">@heroicon('o-arrow-trending-up', 'w-5 h-5 text-emerald-600')</div>
            </div>
            <p class="mt-2 text-2xl font-bold text-gray-900"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(profitEstimate)"></span></p>
            <p class="text-xs text-gray-500 mt-1">potential profit</p>
        </div>
        @endif
    </div>

    {{-- Action Buttons --}}
      <div class="flex flex-wrap items-center gap-2 mb-6">
          @if (auth()->user()?->hasPermission('inventory', 'addRestock'))
          <button @click="openAddProductModal()" class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-lg bg-blue-600 text-xs sm:text-sm font-semibold text-white hover:bg-blue-700 transition-colors shadow-sm shadow-blue-200">
              @heroicon('o-plus', 'w-4 h-4')
              <span class="hidden sm:inline">Add Product</span>
              <span class="sm:hidden">Add</span>
          </button>
          <button @click="openImport()" class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-xs sm:text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
              @heroicon('o-arrow-up-tray', 'w-4 h-4')
              <span class="hidden sm:inline">Import Products</span>
              <span class="sm:hidden">Import</span>
          </button>
          @endif
          @if (auth()->user()?->hasPermission('inventory', 'editAdjust'))
          <button @click="openBulkAdjust()" class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-xs sm:text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
              @heroicon('o-adjustments-horizontal', 'w-4 h-4')
              <span class="hidden sm:inline">Bulk Adjust</span>
              <span class="sm:hidden">Bulk</span>
          </button>
          @endif
          @if (auth()->user()?->hasPermission('inventory', 'addRestock'))
          <button @click="openRestock()" class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-xs sm:text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
              @heroicon('o-arrow-path', 'w-4 h-4')
              <span class="hidden sm:inline">Restock</span>
              <span class="sm:hidden">Restock</span>
          </button>
          @endif
          @if (auth()->user()?->hasPermission('inventory', 'transfer'))
          <button @click="openTransfer()" class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-xs sm:text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
              @heroicon('o-arrows-right-left', 'w-4 h-4')
              <span class="hidden sm:inline">Stock Transfer</span>
              <span class="sm:hidden">Transfer</span>
          </button>
          @endif
          <button @click="showFilterModal = true" class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-xs sm:text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
              @heroicon('o-funnel', 'w-4 h-4')
              Filter
          </button>
      </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-6">
        <div class="px-5 py-4 border-b border-gray-100">
              <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3">
                  <div class="relative flex-1 w-full sm:max-w-xs">
                      <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                      </svg>
                      <input type="text" x-model.debounce.300ms="searchQuery" placeholder="Search products..." class="w-full h-9 pl-9 pr-4 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-blue-500 outline-none transition-all">
                  </div>
                  <div class="flex items-center gap-2 w-full sm:w-auto">
                      <select x-model="counterFilter" class="h-9 px-3 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:border-blue-500 outline-none w-full sm:w-auto">
                          <option value="all">All Counters</option>
                          <template x-for="counter in counters" :key="counter">
                              <option :value="counter" x-text="counter"></option>
                          </template>
                      </select>
                  </div>
              </div>
            {{-- Category Tabs --}}
            <div class="flex items-center gap-2 mt-3 overflow-x-auto no-scrollbar">
                <template x-for="cat in categories" :key="cat.id">
                    <button @click="setCategory(cat.id)"
                            :class="activeCategory === cat.id ? 'bg-blue-600 text-white shadow-sm shadow-blue-200' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="flex-shrink-0 flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-150">
                        <span x-text="cat.icon"></span>
                        <span x-text="cat.label"></span>
                    </button>
                </template>
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto inv-scroll">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/50">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Product</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Counter</th>
                        <th class="text-center px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Expiry</th>
                        <th class="text-center px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Stock</th>
                        <th class="text-center px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Reorder Lvl</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        @if (auth()->user()?->hasPermission('inventory', 'value'))
                        <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Buying Price</th>
                        <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Selling Price</th>
                        @endif
                        <th class="text-center px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <template x-for="product in filteredProducts" :key="product.id">
                        <tr class="hover:bg-gray-50/40 transition-colors" :class="isOutOfStock(product) ? 'bg-red-50/30' : isLowStock(product) ? 'bg-amber-50/20' : ''">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="text-lg" x-text="product.cat === 'spirits' ? '🥃' : product.cat === 'beer' ? '🍺' : product.cat === 'wine' ? '🍷' : product.cat === 'softdrinks' ? '🥤' : product.cat === 'water' ? '💧' : product.cat === 'food' ? '🍔' : '📦'"></span>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900" x-text="product.name"></p>
                                        <p class="text-[10px] text-gray-400" x-text="product.sup"></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-600" x-text="product.counter"></span>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium"
                                      :class="expiryToneClasses(expiryStatus(product).tone)"
                                      x-text="expiryStatus(product).label"></span>
                                <p x-show="productExpiry(product)" class="mt-0.5 text-[10px] text-gray-400 font-mono" x-text="formatExpiry(product)"></p>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <span class="text-sm font-bold" :class="isOutOfStock(product) ? 'text-red-600' : isLowStock(product) ? 'text-amber-600' : 'text-gray-900'" x-text="product.stock"></span>
                                <span class="text-[10px] text-gray-400 ml-0.5" x-text="product.unit"></span>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <span class="text-sm text-gray-500" x-text="product.reorder"></span>
                            </td>
                            <td class="px-5 py-3">
                                <div class="w-20">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-[10px] font-medium"
                                              :class="isOutOfStock(product) ? 'text-red-600' : isLowStock(product) ? 'text-amber-600' : 'text-emerald-600'"
                                              x-text="isOutOfStock(product) ? 'Out of Stock' : isLowStock(product) ? 'Low Stock' : 'In Stock'"></span>
                                    </div>
                                    <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full transition-all duration-500"
                                             :class="stockBarColor(product)"
                                             :style="'width: ' + stockBarWidth(product) + '%'"></div>
                                    </div>
                                </div>
                            </td>
                            @if (auth()->user()?->hasPermission('inventory', 'value'))
                            <td class="px-5 py-3 text-right">
                                <span class="text-sm text-gray-900"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(product.buyPrice)"></span></span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <span class="text-sm font-semibold text-gray-900"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(product.sellPrice)"></span></span>
                            </td>
                            @endif
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-center gap-1.5">
                                    @if (auth()->user()?->hasPermission('inventory', 'editAdjust'))
                                    <button @click="openEdit(product)" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold text-emerald-600 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition-colors" title="Edit">
                                        @heroicon('o-pencil', 'w-4 h-4')
                                    </button>
                                    @endif
                                    @if (auth()->user()?->hasPermission('inventory', 'delete'))
                                    <button @click="openDelete(product)" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition-colors" title="Delete">
                                        @heroicon('o-trash', 'w-4 h-4')
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    @if (auth()->user()?->hasPermission('inventory', 'addRestock'))
    {{-- Add Product Modal --}}
    <template x-teleport="body">
        <div x-show="showAddProductModal"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             @click.away="showAddProductModal = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showAddProductModal = false"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg z-10 max-h-[90vh] overflow-y-auto"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 sticky top-0 bg-white z-10">
                    <h3 class="text-lg font-bold text-gray-900">Add New Product</h3>
                    <button @click="showAddProductModal = false" class="p-1 text-gray-400 hover:text-gray-600">@heroicon('o-x-mark', 'w-5 h-5')</button>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div class="flex items-center gap-4 rounded-xl border border-dashed border-gray-300 bg-gray-50 p-3">
                        <template x-if="newProduct.image">
                            <img :src="newProduct.image" alt="Product image preview" class="h-16 w-16 rounded-lg border border-gray-200 object-cover">
                        </template>
                        <template x-if="!newProduct.image">
                            <div class="flex h-16 w-16 items-center justify-center rounded-lg border border-gray-200 bg-white text-xs text-gray-400">No image</div>
                        </template>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-800">Product image</p>
                            <p class="text-xs text-gray-500">Choose an image up to 2 MB. It will appear on the POS.</p>
                        </div>
                        <label class="cursor-pointer rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-100">
                            <span x-text="productImageLoading ? 'Loading…' : 'Choose image'"></span>
                            <input type="file" accept="image/*" class="hidden" @change="handleProductImageUpload($event, 'new')">
                        </label>
                        <button type="button" x-show="newProduct.image" @click="newProduct.image = ''" class="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600" aria-label="Remove product image">
                            @heroicon('o-trash', 'w-4 h-4')
                        </button>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Product Name <span class="text-red-500">*</span></label>
                            <input type="text" x-model="newProduct.name" @input="delete newProductErrors.name" placeholder="e.g. Cocacola" class="w-full h-10 px-4 text-sm border rounded-xl outline-none transition-all" :class="newProductErrors.name ? 'bg-red-50 border-red-400 focus:border-red-500 focus:ring-2 focus:ring-red-500/20' : 'bg-gray-50 border-gray-300 focus:border-blue-500'">
                            <p x-show="newProductErrors.name" x-cloak class="mt-1 text-xs text-red-600" x-text="newProductErrors.name"></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Category</label>
                            <select x-model="newProduct.cat" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none">
                                <template x-for="cat in categories.filter(function(c){return c.id !== 'all';})" :key="cat.id">
                                    <option :value="cat.id" x-text="cat.label"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Unit</label>
                            <select x-model="newProduct.unit" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none">
                                <template x-for="u in units" :key="u">
                                    <option :value="u" x-text="u"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Stock Quantity <span class="text-red-500">*</span></label>
                            <input type="number" x-model="newProduct.stock" min="0" @input="delete newProductErrors.stock" class="w-full h-10 px-4 text-sm border rounded-xl outline-none transition-all" :class="newProductErrors.stock ? 'bg-red-50 border-red-400 focus:border-red-500 focus:ring-2 focus:ring-red-500/20' : 'bg-gray-50 border-gray-300 focus:border-blue-500'">
                            <p x-show="newProductErrors.stock" x-cloak class="mt-1 text-xs text-red-600" x-text="newProductErrors.stock"></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Reorder Level <span class="text-xs font-normal text-gray-400">(optional)</span></label>
                            <input type="number" x-model="newProduct.reorder" min="0" @input="delete newProductErrors.reorder" class="w-full h-10 px-4 text-sm border rounded-xl outline-none transition-all" :class="newProductErrors.reorder ? 'bg-red-50 border-red-400 focus:border-red-500 focus:ring-2 focus:ring-red-500/20' : 'bg-gray-50 border-gray-300 focus:border-blue-500'">
                            <p x-show="newProductErrors.reorder" x-cloak class="mt-1 text-xs text-red-600" x-text="newProductErrors.reorder"></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Buying Price (<span x-text="BmsMoney.symbol"></span>) <span class="text-red-500">*</span></label>
                            <input type="number" x-model="newProduct.buyPrice" min="0" @input="delete newProductErrors.buyPrice" class="w-full h-10 px-4 text-sm border rounded-xl outline-none transition-all" :class="newProductErrors.buyPrice ? 'bg-red-50 border-red-400 focus:border-red-500 focus:ring-2 focus:ring-red-500/20' : 'bg-gray-50 border-gray-300 focus:border-blue-500'">
                            <p x-show="newProductErrors.buyPrice" x-cloak class="mt-1 text-xs text-red-600" x-text="newProductErrors.buyPrice"></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Selling Price (<span x-text="BmsMoney.symbol"></span>) <span class="text-red-500">*</span></label>
                            <input type="number" x-model="newProduct.sellPrice" min="0" @input="delete newProductErrors.sellPrice" class="w-full h-10 px-4 text-sm border rounded-xl outline-none transition-all" :class="newProductErrors.sellPrice ? 'bg-red-50 border-red-400 focus:border-red-500 focus:ring-2 focus:ring-red-500/20' : 'bg-gray-50 border-gray-300 focus:border-blue-500'">
                            <p x-show="newProductErrors.sellPrice" x-cloak class="mt-1 text-xs text-red-600" x-text="newProductErrors.sellPrice"></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Counter</label>
                            <select x-model="newProduct.counter" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none">
                                <template x-for="counter in counters" :key="counter">
                                    <option :value="counter" x-text="counter"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Expiry Date <span class="text-xs font-normal text-gray-400">(optional)</span></label>
                            <div class="relative">
                                <input type="date" x-model="newProduct.expiryDate" @input="delete newProductErrors.expiryDate"
                                       class="w-full h-10 px-4 pr-10 text-sm border rounded-xl outline-none transition-all"
                                       :class="newProductErrors.expiryDate
                                            ? 'bg-red-50 border-red-400 focus:border-red-500 focus:ring-2 focus:ring-red-500/20'
                                            : 'bg-gray-50 border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20'">
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                    <span x-show="!newProduct.expiryDate" class="text-gray-400">@heroicon('o-calendar-days', 'w-4 h-4')</span>
                                    <button type="button" x-show="newProduct.expiryDate" x-cloak @click="newProduct.expiryDate = ''"
                                            class="pointer-events-auto text-gray-400 hover:text-gray-600" title="Clear expiry date">
                                        @heroicon('o-x-circle', 'w-4 h-4')
                                    </button>
                                </div>
                            </div>
                            <p x-show="newProductErrors.expiryDate" x-cloak class="mt-1 text-xs text-red-600" x-text="newProductErrors.expiryDate"></p>
                            <p x-show="!newProductErrors.expiryDate && newProduct.expiryDate" x-cloak
                               class="mt-1 text-xs font-medium"
                               :class="expiryStatus(newProduct).tone === 'red' ? 'text-red-600' : expiryStatus(newProduct).tone === 'amber' ? 'text-amber-600' : 'text-emerald-600'"
                               x-text="formatExpiry(newProduct) + ' — ' + expiryStatus(newProduct).label"></p>
                            <p x-show="!newProduct.expiryDate" class="mt-1 text-[11px] text-gray-400">Leave blank for non-perishable stock like spirits.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Supplier</label>
                            <input type="text" x-model="newProduct.sup" placeholder="e.g. Distillers Ltd" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none">
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex gap-2 sticky bottom-0">
                    <button @click="showAddProductModal = false" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white">Cancel</button>
                    <button @click="saveNewProduct()" :disabled="saving === 'product'" class="flex-1 py-2.5 rounded-xl bg-blue-600 text-sm font-semibold text-white hover:bg-blue-700 shadow-sm shadow-blue-200 disabled:opacity-60 inline-flex items-center justify-center gap-2">
                        <span x-show="saving === 'product'" class="bms-progress-ring"></span>
                        <span x-text="saving === 'product' ? 'Saving...' : 'Add Product'"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>
    @endif

    @if (auth()->user()?->hasPermission('inventory', 'editAdjust'))
    {{-- Edit Product Modal --}}
    <template x-teleport="body">
        <div x-show="showEditModal"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             @click.away="showEditModal = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showEditModal = false"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg z-10 max-h-[90vh] overflow-y-auto"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 sticky top-0 bg-white z-10">
                    <h3 class="text-lg font-bold text-gray-900">Edit Product</h3>
                    <button @click="showEditModal = false" class="p-1 text-gray-400 hover:text-gray-600">@heroicon('o-x-mark', 'w-5 h-5')</button>
                </div>
                <div class="px-6 py-5 space-y-4" x-show="selectedProduct">
                    <div class="flex items-center gap-4 rounded-xl border border-dashed border-gray-300 bg-gray-50 p-3">
                        <template x-if="selectedProduct && selectedProduct.image">
                            <img :src="selectedProduct.image" alt="Product image preview" class="h-16 w-16 rounded-lg border border-gray-200 object-cover">
                        </template>
                        <template x-if="selectedProduct && !selectedProduct.image">
                            <div class="flex h-16 w-16 items-center justify-center rounded-lg border border-gray-200 bg-white text-xs text-gray-400">No image</div>
                        </template>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-800">Product image</p>
                            <p class="text-xs text-gray-500">Choose an image up to 2 MB. It will appear on the POS.</p>
                        </div>
                        <label class="cursor-pointer rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-100">
                            <span x-text="productImageLoading ? 'Loading…' : 'Choose image'"></span>
                            <input type="file" accept="image/*" class="hidden" @change="handleProductImageUpload($event, 'edit')">
                        </label>
                        <button type="button" x-show="selectedProduct && selectedProduct.image" @click="selectedProduct.image = ''" class="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600" aria-label="Remove product image">
                            @heroicon('o-trash', 'w-4 h-4')
                        </button>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Product Name</label>
                            <input type="text" x-model="selectedProduct.name" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Category</label>
                            <select x-model="selectedProduct.cat" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none">
                                <template x-for="cat in categories.filter(function(c){return c.id !== 'all';})" :key="cat.id">
                                    <option :value="cat.id" x-text="cat.label"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Unit</label>
                            <select x-model="selectedProduct.unit" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none">
                                <template x-for="u in units" :key="u">
                                    <option :value="u" x-text="u"></option>
                                </template>
                            </select>
                        </div>
                        <div class="sm:col-span-2 rounded-xl border border-blue-100 bg-blue-50 px-3 py-2.5 text-xs text-blue-800">
                            Current stock: <strong x-text="selectedProduct?.stock"></strong> <span x-text="selectedProduct?.unit"></span>.
                            Change stock using <strong>Adjust</strong> so the movement is recorded.
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Reorder Level</label>
                            <input type="number" x-model="selectedProduct.reorder" min="0" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Buying Price (<span x-text="BmsMoney.symbol"></span>)</label>
                            <input type="number" x-model="selectedProduct.buyPrice" min="0" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Selling Price (<span x-text="BmsMoney.symbol"></span>)</label>
                            <input type="number" x-model="selectedProduct.sellPrice" min="0" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Counter</label>
                            <input type="text" :value="selectedProduct.counter" readonly class="w-full h-10 px-4 text-sm bg-gray-100 border border-gray-300 rounded-xl text-gray-500">
                            <p class="mt-1 text-[11px] text-gray-400">Use Stock Transfer to move quantities between counters.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Supplier</label>
                            <input type="text" x-model="selectedProduct.sup" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none">
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex gap-2 sticky bottom-0">
                    <button @click="showEditModal = false" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white">Cancel</button>
                    <button @click="saveEdit()" :disabled="saving === 'edit'" class="flex-1 py-2.5 rounded-xl bg-blue-600 text-sm font-semibold text-white hover:bg-blue-700 shadow-sm shadow-blue-200 disabled:opacity-60 inline-flex items-center justify-center gap-2">
                        <span x-show="saving === 'edit'" class="bms-progress-ring"></span>
                        <span x-text="saving === 'edit' ? 'Saving...' : 'Save Changes'"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>
    @endif

    @if (auth()->user()?->hasPermission('inventory', 'delete'))
    {{-- Delete Confirmation Modal --}}
    <template x-teleport="body">
        <div x-show="showDeleteModal"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             @click.away="showDeleteModal = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showDeleteModal = false"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm z-10"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
                <div class="px-6 py-5 text-center">
                    <div class="inline-flex items-center justify-center w-14 h-14 bg-red-100 rounded-full mb-4">
                        @heroicon('o-exclamation-triangle', 'w-7 h-7 text-red-600')
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Delete Product</h3>
                    <p class="text-sm text-gray-500 mb-1">Are you sure you want to delete</p>
                    <p class="text-base font-bold text-gray-900" x-text="productToDelete?.name"></p>
                    <p class="text-xs text-gray-400 mt-1">The product can be restored from Settings &gt; Trash.</p>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex gap-2">
                    <button @click="showDeleteModal = false" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white">Cancel</button>
                    <button @click="confirmDelete()" :disabled="saving === 'delete'" class="flex-1 py-2.5 rounded-xl bg-red-600 text-sm font-semibold text-white hover:bg-red-700 shadow-sm shadow-red-200 disabled:opacity-60 inline-flex items-center justify-center gap-2">
                        <span x-show="saving === 'delete'" class="bms-progress-ring"></span>
                        <span x-text="saving === 'delete' ? 'Deleting...' : 'Delete'"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>
    @endif

    @if (auth()->user()?->hasPermission('inventory', 'editAdjust'))
    {{-- Stock Adjustment Modal --}}
    <template x-teleport="body">
        <div x-show="showAdjustment"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             @click.away="showAdjustment = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showAdjustment = false"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm z-10"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900">Stock Adjustment</h3>
                    <button @click="showAdjustment = false" class="p-1 text-gray-400 hover:text-gray-600">@heroicon('o-x-mark', 'w-5 h-5')</button>
                </div>
                <div class="px-6 py-5 space-y-4" x-show="selectedProduct">
                    <div class="bg-gray-50 rounded-xl p-3 flex items-center gap-3">
                        <span class="text-2xl" x-text="selectedProduct?.cat === 'spirits' ? '🥃' : '📦'"></span>
                        <div>
                            <p class="text-sm font-semibold text-gray-900" x-text="selectedProduct?.name"></p>
                            <p class="text-xs text-gray-500">Current stock: <span class="font-bold" x-text="selectedProduct?.stock"></span> <span x-text="selectedProduct?.unit"></span></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 p-1 bg-gray-100 rounded-xl">
                        <button @click="adjType = 'add'" :class="adjType === 'add' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500'" class="flex-1 py-2 rounded-lg text-sm font-medium transition-all">Add Stock</button>
                        <button @click="adjType = 'remove'" :class="adjType === 'remove' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500'" class="flex-1 py-2 rounded-lg text-sm font-medium transition-all">Remove Stock</button>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Quantity</label>
                        <input type="number" x-model="adjQuantity" min="1" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Reason</label>
                        <select x-model="adjReason" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none">
                            <option value="">Select reason...</option>
                            <option value="delivery">New Delivery</option>
                            <option value="damaged">Damaged / Expired</option>
                            <option value="count">Inventory Count</option>
                            <option value="transfer">Transfer</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex gap-2">
                    <button @click="showAdjustment = false" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white">Cancel</button>
                    <button @click="applyAdjustment()" :disabled="saving === 'adjustment'" class="flex-1 py-2.5 rounded-xl bg-blue-600 text-sm font-semibold text-white hover:bg-blue-700 shadow-sm shadow-blue-200 disabled:opacity-60 inline-flex items-center justify-center gap-2">
                        <span x-show="saving === 'adjustment'" class="bms-progress-ring"></span>
                        <span x-text="saving === 'adjustment' ? 'Saving...' : 'Apply'"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- Bulk Adjust Modal --}}
    <template x-teleport="body">
        <div x-show="showBulkAdjustModal"
             x-cloak
             class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto overscroll-contain p-3 sm:p-6"
             @keydown.escape.window="closeBulkAdjustModal()"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="fixed inset-0 bg-black/30" @click="closeBulkAdjustModal()"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl my-auto z-10 flex flex-col max-h-[70vh] overflow-hidden"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
                {{-- Header --}}
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-200 flex-shrink-0">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
                            @heroicon('o-adjustments-horizontal', 'w-5 h-5 text-blue-600')
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-lg font-bold text-gray-900">Bulk Stock Adjustment</h3>
                            <p class="text-xs text-gray-500">Enter a quantity and pick a reason per product. Leave a row blank to skip it.</p>
                        </div>
                    </div>
                    <button @click="closeBulkAdjustModal()" class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors flex-shrink-0">@heroicon('o-x-mark', 'w-5 h-5')</button>
                </div>

                {{-- Legend: what each reason does --}}
                <div class="px-5 py-3 bg-gray-50/70 border-b border-gray-100 flex-shrink-0">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <template x-for="r in bulkAdjReasonOptions" :key="r.id">
                            <div class="flex items-start gap-2 px-2.5 py-2 rounded-lg bg-white border" :class="adjToneClass(r.tone)">
                                @heroicon('o-scale', 'w-4 h-4 mt-0.5 flex-shrink-0')
                                <div class="min-w-0">
                                    <p class="text-[11px] font-bold leading-tight" x-text="r.label"></p>
                                    <p class="text-[10px] font-normal opacity-80 leading-tight mt-0.5" x-text="r.hint"></p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Search + Category + Counter --}}
                <div class="px-5 py-3.5 bg-gray-50/70 border-b border-gray-100 flex-shrink-0">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-3">
                        <div class="relative sm:col-span-1">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input type="text" x-model="bulkAdjSearch" placeholder="Search by name or supplier..." class="w-full h-9 pl-9 pr-3 text-sm bg-white border border-gray-200 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                        </div>
                        <select x-model="bulkAdjCategory" class="h-9 px-3 text-sm bg-white border border-gray-200 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none">
                            <option value="all">All Categories</option>
                            <template x-for="cat in bulkAdjCategories" :key="cat.id"><option :value="cat.id" x-text="cat.label"></option></template>
                        </select>
                        <select x-model="bulkAdjCounter" class="h-9 px-3 text-sm bg-white border border-gray-200 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none">
                            <option value="all">All Counters</option>
                            <template x-for="counter in counters" :key="counter"><option :value="counter" x-text="counter"></option></template>
                        </select>
                    </div>
                </div>

                {{-- Table --}}
                <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10">
                            <tr class="border-b border-gray-100 bg-white">
                                <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Product</th>
                                <th class="text-right px-2 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Current</th>
                                <th class="text-left px-2 py-2.5 text-xs font-semibold text-blue-600 uppercase tracking-wider w-20 sm:w-24">Qty</th>
                                <th class="text-left px-2 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider w-28 sm:w-36">Reason</th>
                                <th class="text-right px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">New Stock</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <template x-for="product in bulkAdjList" :key="product.id">
                                <tr class="transition-colors"
                                    :class="bulkAdjOverflow(product) ? 'bg-red-50/60' : (bulkAdjQty(product.id) > 0 ? (bulkAdjIsBalance(product.id) ? 'bg-blue-50/40' : 'bg-amber-50/40') : 'hover:bg-gray-50/50')">
                                    <td class="px-5 py-2.5">
                                        <div class="flex items-center gap-2.5">
                                            <span class="text-lg flex-shrink-0" x-text="adjCategoryIcon(product.cat)"></span>
                                            <div class="min-w-0">
                                                <p class="font-medium text-gray-900 truncate" x-text="product.name"></p>
                                                <p class="text-[11px] text-gray-400 truncate"><span x-text="product.counter"></span> &middot; <span x-text="product.sup"></span></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-2 py-2.5 text-right whitespace-nowrap">
                                        <span class="font-medium text-gray-700" x-text="formatNumber(product.stock)"></span>
                                        <span class="text-[11px] text-gray-400" x-text="product.unit"></span>
                                    </td>
                                    <td class="px-2 py-2.5">
                                        <div class="relative">
                                            <input type="number" min="0" step="1" x-model="bulkAdjLines[product.id]" placeholder="0"
                                                   class="w-full h-9 pl-2 pr-7 text-sm text-right bg-white border rounded-lg outline-none transition-all focus:ring-2"
                                                   :class="bulkAdjOverflow(product) ? 'border-red-400 focus:border-red-500 focus:ring-red-500/20' : (bulkAdjQty(product.id) > 0 ? (bulkAdjIsBalance(product.id) ? 'border-blue-400 focus:border-blue-500 focus:ring-blue-500/20' : 'border-amber-400 focus:border-amber-500 focus:ring-amber-500/20') : 'border-gray-200 focus:border-blue-500 focus:ring-blue-500/20')">
                                            <span class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] text-gray-400 pointer-events-none" x-text="product.unit"></span>
                                        </div>
                                    </td>
                                    <td class="px-2 py-2.5">
                                        <div class="relative">
                                            <select x-model="bulkAdjReasons[product.id]"
                                                    class="w-full h-9 pl-2 pr-7 text-xs font-medium bg-white border rounded-lg outline-none transition-all focus:ring-2 appearance-none cursor-pointer"
                                                    :class="bulkAdjReasonFor(product.id).tone === 'red' ? 'border-red-300 text-red-700 focus:border-red-500 focus:ring-red-500/20' : (bulkAdjReasonFor(product.id).tone === 'amber' ? 'border-amber-300 text-amber-700 focus:border-amber-500 focus:ring-amber-500/20' : 'border-blue-300 text-blue-700 focus:border-blue-500 focus:ring-blue-500/20')">
                                                <template x-for="r in bulkAdjReasonOptions" :key="r.id">
                                                    <option :value="r.id" x-text="r.label"></option>
                                                </template>
                                            </select>
                                            <span class="absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400">@heroicon('o-chevron-down', 'w-3.5 h-3.5')</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-2.5 text-right whitespace-nowrap">
                                        <span class="font-bold"
                                              :class="bulkAdjOverflow(product) ? 'text-red-600' : (bulkAdjQty(product.id) > 0 ? (bulkAdjIsBalance(product.id) ? 'text-blue-600' : 'text-amber-600') : 'text-gray-400')"
                                              x-text="bulkAdjNewQty(product)"></span>
                                        <span class="text-[11px] text-gray-400" x-text="product.unit"></span>
                                        <span class="block text-[10px] font-medium text-red-600" x-show="bulkAdjOverflow(product)">Exceeds current stock</span>
                                        <span class="block text-[10px] text-gray-500"
                                              x-show="!bulkAdjOverflow(product) && bulkAdjQty(product.id) > 0"
                                              :class="bulkAdjIsBalance(product.id) && bulkAdjNewQty(product) >= product.stock ? 'text-emerald-600' : 'text-red-600'"
                                              x-text="(bulkAdjIsBalance(product.id)
                                                          ? (bulkAdjNewQty(product) >= product.stock ? '+' : '') + BmsMoney.symbol + ' ' + formatMoney((bulkAdjNewQty(product) - product.stock) * product.buyPrice)
                                                          : '- ' + BmsMoney.symbol + ' ' + formatMoney(bulkAdjQty(product.id) * product.buyPrice))"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <div x-show="bulkAdjList.length === 0" class="py-12 text-center text-gray-400 text-sm">No products match your search.</div>
                </div>

                {{-- Footer --}}
                <div class="px-5 py-3.5 bg-gray-50 border-t border-gray-200 flex-shrink-0">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs min-w-0">
                            <template x-for="r in bulkAdjReasonSummary" :key="r.id">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold ring-1 whitespace-nowrap" :class="adjToneClass(r.tone)" x-text="r.label + ' × ' + r.count"></span>
                            </template>
                            <span class="text-gray-500 whitespace-nowrap">Items: <span class="font-bold text-gray-900" x-text="bulkAdjLineCount"></span></span>
                            <span class="text-gray-500 whitespace-nowrap">Total qty: <span class="font-bold text-gray-900" x-text="formatNumber(bulkAdjTotalQty)"></span></span>
                            <span class="text-gray-500 whitespace-nowrap">Value impact:
                                <span class="font-bold" :class="bulkAdjValueImpact < 0 ? 'text-red-600' : (bulkAdjValueImpact > 0 ? 'text-emerald-600' : 'text-gray-900')"
                                      x-text="(bulkAdjValueImpact < 0 ? '- ' : (bulkAdjValueImpact > 0 ? '+ ' : '')) + BmsMoney.symbol + ' ' + formatMoney(Math.abs(bulkAdjValueImpact))"></span>
                            </span>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <button @click="closeBulkAdjustModal()" class="flex-1 sm:flex-none px-4 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white transition-colors">Cancel</button>
                            <button @click="saveBulkAdjust()" :disabled="saving === 'bulk-adjustment'" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-blue-600 text-sm font-semibold text-white hover:bg-blue-700 transition-colors shadow-sm shadow-blue-200 whitespace-nowrap disabled:opacity-60">
                                <span x-show="saving === 'bulk-adjustment'" class="bms-progress-ring"></span>
                                <span x-show="saving !== 'bulk-adjustment'">@heroicon('o-check', 'w-4 h-4')</span>
                                <span x-text="saving === 'bulk-adjustment' ? 'Saving...' : 'Save Adjustment'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
    @endif

    @if (auth()->user()?->hasPermission('inventory', 'addRestock'))
    {{-- Import Modal --}}
    <template x-teleport="body">
        <div x-show="showImportModal"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4"
             @keydown.escape.window="showImportModal = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showImportModal = false"></div>

            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl z-10 flex flex-col overflow-hidden max-h-[92vh] sm:max-h-[88vh]"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">

                {{-- Header --}}
                <div class="flex-shrink-0 flex items-center justify-between px-5 sm:px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
                            @heroicon('o-arrow-up-tray', 'w-5 h-5 text-blue-600')
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-lg font-bold text-gray-900 leading-tight">Import Products</h3>
                            <p class="text-xs text-gray-500">Upload a filled template — every product is listed before anything is saved.</p>
                        </div>
                    </div>
                    <button @click="showImportModal = false; resetImport();" class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors flex-shrink-0">@heroicon('o-x-mark', 'w-5 h-5')</button>
                </div>

                {{-- Scrollable body --}}
                <div class="flex-1 overflow-y-auto">

                    {{-- Step 1: template + file picker --}}
                    <div class="px-5 sm:px-6 py-5 border-b border-gray-100">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                            <a href="{{ route('inventory.products.template') }}"
                               class="group flex items-start gap-3 p-4 rounded-xl border border-blue-200 bg-gradient-to-br from-blue-50 to-white hover:from-blue-100 hover:border-blue-300 transition-all">
                                <div class="w-9 h-9 rounded-lg bg-blue-600 text-white flex items-center justify-center flex-shrink-0 shadow-sm shadow-blue-200 group-hover:scale-105 transition-transform">
                                    @heroicon('o-arrow-down-tray', 'w-4 h-4')
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-blue-900">Download Excel Template</p>
                                    <p class="text-xs text-blue-700/80 mt-0.5 leading-relaxed">
                                        Formatted <span class="font-semibold">.xlsx</span> with {{ count($importColumns ?? []) }} columns, locked headers and editable product rows. Product name, stock quantity and both prices are required; reorder level, expiry date and supplier are optional.
                                    </p>
                                </div>
                            </a>

                            <label class="group flex items-center justify-center gap-3 px-4 py-4 rounded-xl border-2 border-dashed cursor-pointer transition-all"
                                   :class="importFile ? 'border-blue-400 bg-blue-50/50' : 'border-gray-300 hover:border-blue-400 hover:bg-blue-50/30'">
                                <input type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="handleImportFile($event)" :disabled="importLoading">

                                <template x-if="importLoading">
                                    <div class="flex items-center gap-3">
                                        <svg class="animate-spin h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        <span class="text-sm font-medium text-blue-700">Reading file…</span>
                                    </div>
                                </template>
                                @endif

                                <template x-if="!importLoading && !importFile">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-lg bg-gray-100 text-gray-500 flex items-center justify-center flex-shrink-0 group-hover:bg-blue-100 group-hover:text-blue-600 transition-colors">
                                            @heroicon('o-document-arrow-up', 'w-4 h-4')
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-800">Choose your file</p>
                                            <p class="text-xs text-gray-500 mt-0.5">.xlsx, .xls or .csv</p>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="!importLoading && importFile">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-9 h-9 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center flex-shrink-0">
                                            @heroicon('o-document-check', 'w-4 h-4')
                                        </div>
                                        <div class="min-w-0 text-left">
                                            <p class="text-sm font-semibold text-gray-900 truncate" x-text="importFile?.name"></p>
                                            <p class="text-xs text-gray-500 mt-0.5">
                                                <span x-text="importPreview ? importPreview.length + ' product' + (importPreview.length === 1 ? '' : 's') + ' found' : 'Click to change file'"></span>
                                            </p>
                                        </div>
                                    </div>
                                </template>
                            </label>
                        </div>

                        {{-- Fallback for direct downloads without the template --}}
                        <details class="mt-3 group">
                            <summary class="cursor-pointer text-xs text-gray-500 hover:text-gray-700 select-none inline-flex items-center gap-1 list-none">
                                @heroicon('o-chevron-down', 'w-3.5 h-3.3 transition-transform group-open:rotate-180')
                                Expected column order
                            </summary>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($importColumns ?? [] as $col)
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-gray-100 text-[11px] font-medium text-gray-700 border border-gray-200">
                                        {{ $col['label'] }}
                                        @if ($col['required'] ?? false)
                                            <span class="text-red-500 font-bold">*</span>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                            <p class="mt-2 text-[11px] text-gray-400">Column order does not matter — the importer matches headers by name. Columns marked <span class="text-red-500 font-bold">*</span> are required.</p>
                        </details>

                        {{-- Applied to every imported row --}}
                        <div class="mt-4 rounded-xl border border-blue-200 bg-blue-50/60 p-4">
                            <div class="flex items-start gap-2.5 mb-3">
                                <div class="w-6 h-6 rounded-md bg-blue-600 text-white flex items-center justify-center flex-shrink-0 mt-0.5">
                                    @heroicon('o-swatch', 'w-3.5 h-3.5')
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-blue-900">Applied to every product you import</p>
                                    <p class="text-xs text-blue-700/80 mt-0.5 leading-relaxed">
                                        These are not in the file. Pick them once and every row is created with the same values.
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-blue-900 mb-1.5">Category</label>
                                    <select x-model="importCategory"
                                            class="w-full rounded-lg border-blue-200 bg-white text-sm text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                        <template x-for="c in categories.filter(c => c.id !== 'all')" :key="c.id">
                                            <option :value="c.id" x-text="c.label"></option>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-blue-900 mb-1.5">Counter</label>
                                    <select x-model="importCounter"
                                            class="w-full rounded-lg border-blue-200 bg-white text-sm text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                        <template x-for="c in counters" :key="c">
                                            <option :value="c" x-text="c"></option>
                                        </template>
                                    </select>
                                </div>
                            </div>

                            <p class="mt-3 flex items-start gap-1.5 text-[11px] text-blue-800/80 leading-relaxed">
                                @heroicon('o-information-circle', 'w-3.5 h-3.5 mt-px flex-shrink-0')
                                <span>Every product is also created with the unit <span class="font-semibold" x-text="importUnitDefault"></span>. You can change that per product afterwards.</span>
                            </p>
                        </div>
                    </div>

                    {{-- Error --}}
                    <div x-show="importError" x-cloak class="mx-5 sm:mx-6 mt-4 rounded-xl border border-red-200 bg-red-50 p-3.5 flex items-start gap-2.5">
                        @heroicon('o-exclamation-triangle', 'w-4.5 h-4.5 text-red-500 mt-0.5 flex-shrink-0')
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-red-900">Could not read that file</p>
                            <p class="text-xs text-red-700 mt-0.5" x-text="importError"></p>
                        </div>
                    </div>

                    {{-- Step 2: full product preview --}}
                    <div x-show="importPreview && importPreview.length > 0" x-cloak class="px-5 sm:px-6 py-5">

                        <div x-show="importUnknownColumns.length" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3.5 py-2.5">
                            <p class="text-xs text-amber-800 leading-relaxed">
                                <span class="font-semibold">Ignored column<span x-show="importUnknownColumns.length > 1">s</span>:</span>
                                <span x-text="importUnknownColumns.join(', ')"></span>.
                                The importer matched the columns it recognises, so these are safe to leave in the file.
                            </p>
                        </div>

                        {{-- Summary --}}
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                            <div class="rounded-xl bg-gray-50 border border-gray-200 px-3.5 py-3">
                                <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wide">Total Rows</p>
                                <p class="mt-1 text-xl font-bold text-gray-900" x-text="importPreview.length"></p>
                            </div>
                            <div class="rounded-xl bg-emerald-50 border border-emerald-200 px-3.5 py-3">
                                <p class="text-[11px] font-medium text-emerald-700 uppercase tracking-wide">Ready to Import</p>
                                <p class="mt-1 text-xl font-bold text-emerald-700" x-text="importValidRows.length"></p>
                            </div>
                            <div class="rounded-xl bg-red-50 border border-red-200 px-3.5 py-3">
                                <p class="text-[11px] font-medium text-red-700 uppercase tracking-wide">With Errors</p>
                                <p class="mt-1 text-xl font-bold text-red-700" x-text="importInvalidRows.length"></p>
                            </div>
                            <div class="rounded-xl bg-blue-50 border border-blue-200 px-3.5 py-3">
                                <p class="text-[11px] font-medium text-blue-700 uppercase tracking-wide">Stock Value</p>
                                <p class="mt-1 text-xl font-bold text-blue-700"><span class="text-sm font-medium"><span x-text="BmsMoney.symbol"></span></span> <span x-text="formatMoney(importTotalValue)"></span></p>
                            </div>
                        </div>

                        {{-- Filter toolbar --}}
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2.5 mb-3">
                            <div class="relative flex-1 min-w-0">
                                @heroicon('o-magnifying-glass', 'absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400')
                                <input type="text" x-model="importSearch" placeholder="Search products in file..."
                                       class="w-full h-9 pl-9 pr-3 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                            </div>
                            <div class="flex items-center gap-1 p-1 bg-gray-100 rounded-lg">
                                <template x-for="f in [
                                    { id: 'all', label: 'All' },
                                    { id: 'valid', label: 'Valid' },
                                    { id: 'invalid', label: 'Errors' }
                                ]" :key="f.id">
                                    <button @click="importFilter = f.id"
                                            class="px-3 py-1.5 rounded-md text-xs font-semibold transition-all"
                                            :class="importFilter === f.id ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                                        <span x-text="f.label"></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        {{-- Product table: every row from the file --}}
                        <div class="border border-gray-200 rounded-xl overflow-hidden">
                            <div class="overflow-auto max-h-[45vh] sm:max-h-[50vh] inv-scroll">
                                    <table class="w-full text-xs min-w-[760px]">
                                    <thead class="sticky top-0 z-10">
                                        <tr class="bg-gray-50 border-b border-gray-200">
                                            <th class="px-3 py-2.5 text-left font-semibold text-gray-600 w-10">#</th>
                                            <th class="px-3 py-2.5 text-left font-semibold text-gray-600">Product Name</th>
                                            <th class="px-3 py-2.5 text-left font-semibold text-gray-600">Expiry</th>
                                            <th class="px-3 py-2.5 text-right font-semibold text-gray-600">Stock</th>
                                            <th class="px-3 py-2.5 text-right font-semibold text-gray-600">Reorder</th>
                                            <th class="px-3 py-2.5 text-right font-semibold text-gray-600">Buy Price</th>
                                            <th class="px-3 py-2.5 text-right font-semibold text-gray-600">Sell Price</th>
                                            <th class="px-3 py-2.5 text-left font-semibold text-gray-600">Supplier</th>
                                            <th class="px-3 py-2.5 text-center font-semibold text-gray-600 w-10">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <template x-for="(row, idx) in importVisibleRows" :key="row._row + '-' + idx">
                                            <tr class="transition-colors"
                                                :class="row._valid ? 'hover:bg-gray-50' : 'bg-red-50/40 hover:bg-red-50/70'">

                                                <td class="px-3 py-2 text-gray-400 font-mono" x-text="row._row"></td>

                                                <td class="px-3 py-2 font-medium text-gray-900">
                                                    <span x-text="row.name || '—'"></span>
                                                    <template x-if="(row._errors || []).length">
                                                        <div class="mt-1 space-y-0.5">
                                                            <template x-for="(err, ei) in row._errors" :key="ei">
                                                                <p class="text-[10px] text-red-600 leading-tight flex items-start gap-1">
                                                                    <span class="flex-shrink-0 mt-px">•</span>
                                                                    <span x-text="err"></span>
                                                                </p>
                                                            </template>
                                                        </div>
                                                    </template>
                                                    <template x-if="row._valid && (row._warnings || []).length">
                                                        <div class="mt-1 space-y-0.5">
                                                            <template x-for="(warn, wi) in row._warnings" :key="wi">
                                                                <p class="text-[10px] text-amber-600 leading-tight flex items-start gap-1">
                                                                    <span class="flex-shrink-0 mt-px">•</span>
                                                                    <span x-text="warn"></span>
                                                                </p>
                                                            </template>
                                                        </div>
                                                    </template>
                                                </td>

                                                <td class="px-3 py-2">
                                                    <template x-if="!row.expiryDate">
                                                        <span class="text-gray-300">—</span>
                                                    </template>
                                                    <template x-if="row.expiryDate">
                                                        <span>
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[10px] font-medium"
                                                                  :class="expiryToneClasses(expiryStatus(row).tone)"
                                                                  x-text="expiryStatus(row).label"></span>
                                                            <span class="block mt-0.5 text-[10px] text-gray-400 font-mono" x-text="row.expiryDate"></span>
                                                        </span>
                                                    </template>
                                                </td>
                                                <td class="px-3 py-2 text-right text-gray-900 tabular-nums" x-text="row.stock ?? '—'"></td>
                                                <td class="px-3 py-2 text-right text-gray-900 tabular-nums" x-text="row.reorder ?? '—'"></td>
                                                <td class="px-3 py-2 text-right text-gray-900 tabular-nums" x-text="row.buyPrice != null ? formatMoney(row.buyPrice) : '—'"></td>
                                                <td class="px-3 py-2 text-right text-gray-900 tabular-nums" x-text="row.sellPrice != null ? formatMoney(row.sellPrice) : '—'"></td>
                                                <td class="px-3 py-2 text-gray-600" x-text="row.sup || '—'"></td>

                                                <td class="px-3 py-2 text-center">
                                                    <template x-if="row._valid">
                                                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-md bg-emerald-100 text-emerald-700 text-[10px] font-bold uppercase">
                                                            OK
                                                        </span>
                                                    </template>
                                                    <template x-if="!row._valid">
                                                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-md bg-red-100 text-red-700 text-[10px] font-bold uppercase">
                                                            Error
                                                        </span>
                                                    </template>
                                                </td>
                                            </tr>
                                        </template>

                                        <tr x-show="importVisibleRows.length === 0">
                                            <td colspan="9" class="px-4 py-10 text-center">
                                                @heroicon('o-inbox', 'w-8 h-8 text-gray-300 mx-auto mb-2')
                                                <p class="text-sm text-gray-500">No rows match this filter.</p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <p class="mt-2 text-[11px] text-gray-400">
                            Showing <span class="font-semibold text-gray-600" x-text="importVisibleRows.length"></span>
                            of <span class="font-semibold text-gray-600" x-text="importPreview.length"></span> rows.
                            Rows marked with an error will be skipped and can be fixed in the file and re-uploaded.
                        </p>

                        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5">
                            <span class="text-[11px] font-medium text-gray-500">Applied to all rows:</span>
                            <span class="inline-flex items-center gap-1 text-[11px] text-gray-700">
                                <span class="text-gray-400">Category</span>
                                <span class="font-semibold" x-text="importCategory"></span>
                            </span>
                            <span class="inline-flex items-center gap-1 text-[11px] text-gray-700">
                                <span class="text-gray-400">Counter</span>
                                <span class="font-semibold" x-text="importCounter"></span>
                            </span>
                            <span class="inline-flex items-center gap-1 text-[11px] text-gray-700">
                                <span class="text-gray-400">Unit</span>
                                <span class="font-semibold" x-text="importUnitDefault"></span>
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="flex-shrink-0 px-5 sm:px-6 py-4 bg-gray-50 border-t border-gray-200 flex flex-col-reverse sm:flex-row sm:items-center gap-3">
                    <div class="flex-1 min-w-0">
                        <template x-if="importPreview && importPreview.length > 0">
                            <p class="text-xs text-gray-500">
                                <span class="font-semibold text-emerald-700" x-text="importValidRows.length"></span> ready
                                <template x-if="importInvalidRows.length > 0">
                                    <span> · <span class="font-semibold text-red-600" x-text="importInvalidRows.length"></span> will be skipped</span>
                                </template>
                            </p>
                        </template>
                        <template x-if="!importPreview">
                            <p class="text-xs text-gray-400">Download the template, fill it in, then choose your file.</p>
                        </template>
                    </div>
                    <div class="flex gap-2">
                        <button @click="showImportModal = false; resetImport();"
                                class="flex-1 sm:flex-none px-4 py-2.5 rounded-xl border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button @click="confirmImport()"
                                :disabled="!importValidRows.length || importLoading || saving === 'import'"
                                class="flex-1 sm:flex-none px-4 py-2.5 rounded-xl bg-blue-600 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-blue-600 transition-colors shadow-sm shadow-blue-200 inline-flex items-center justify-center gap-2">
                            <span x-show="saving === 'import'" class="bms-progress-ring"></span>
                            <span x-show="saving !== 'import'">@heroicon('o-check', 'w-4 h-4')</span>
                            <span x-text="saving === 'import' ? 'Importing...' : (importValidRows.length ? 'Import ' + importValidRows.length + ' Product' + (importValidRows.length === 1 ? '' : 's') : 'Import')"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>

    {{-- Toast --}}
    <template x-teleport="body">
        <div x-show="toast.show" x-cloak class="fixed z-[60] bottom-4 right-4 left-4 sm:left-auto sm:w-80" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
            <div class="flex items-center gap-3 bg-white rounded-xl shadow-lg shadow-black/10 border border-gray-200 px-4 py-3 ring-1"
                 :class="toast.tone === 'error' ? 'ring-red-200' : 'ring-emerald-200'">
                <span class="flex-shrink-0 mt-px" x-show="toast.tone === 'error'">@heroicon('o-exclamation-triangle', 'w-5 h-5 text-red-600')</span>
                <span class="flex-shrink-0 mt-px" x-show="toast.tone !== 'error'">@heroicon('o-check-circle', 'w-5 h-5 text-emerald-600')</span>
                <p class="text-sm font-medium flex-1" :class="toast.tone === 'error' ? 'text-red-900' : 'text-gray-900'" x-text="toast.text"></p>
                <button @click="toast.show = false" class="text-gray-400 hover:text-gray-600 flex-shrink-0">@heroicon('o-x-mark', 'w-4 h-4')</button>
            </div>
        </div>
    </template>

    @if (auth()->user()?->hasPermission('inventory', 'addRestock'))
    {{-- Restock Modal (large) --}}
    <template x-teleport="body">
        <div x-show="showRestockModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden p-3 sm:p-6" @keydown.escape.window="closeRestockModal()" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <div class="fixed inset-0 bg-black/30" @click="closeRestockModal()"></div>
            <div class="relative z-10 flex flex-col w-full h-[560px] sm:h-[680px] max-h-[calc(100dvh-1.5rem)] sm:max-h-[calc(100dvh-3rem)] overflow-hidden bg-white rounded-2xl shadow-2xl" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                {{-- Header --}}
                <div class="flex-shrink-0 flex items-center justify-between px-5 py-3.5 border-b border-gray-200">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center flex-shrink-0">
                            @heroicon('o-arrow-path', 'w-5 h-5 text-emerald-600')
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">Restock Products</h3>
                            <p class="text-xs text-gray-500">Enter the quantity received for each product. Leave a row blank to skip it.</p>
                        </div>
                    </div>
                    <button @click="closeRestockModal()" class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">@heroicon('o-x-mark', 'w-5 h-5')</button>
                </div>

                {{-- Toolbar --}}
                <div class="flex-shrink-0 px-5 py-3 bg-gray-50/70 border-b border-gray-100">
                    <div class="flex items-center gap-2 sm:gap-3">
                        <div class="relative flex-1 min-w-0">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input type="text" x-model="restockSearch" placeholder="Search by product or supplier..." class="w-full h-9 pl-9 pr-3 text-sm bg-white border border-gray-200 rounded-lg focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none transition-all">
                        </div>
                        <select x-model="restockCounter" class="flex-shrink-0 w-36 sm:w-44 h-9 px-3 text-sm bg-white border border-gray-200 rounded-lg focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none">
                            <option value="all">All Counters</option>
                            <template x-for="counter in counters" :key="counter">
                                <option :value="counter" x-text="counter"></option>
                            </template>
                        </select>
                        <select x-model="restockCategory" class="flex-shrink-0 w-36 sm:w-44 h-9 px-3 text-sm bg-white border border-gray-200 rounded-lg focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none">
                            <option value="all">All Categories</option>
                            <template x-for="cat in categories.filter(function(c){return c.id !== 'all';})" :key="cat.id">
                                <option :value="cat.id" x-text="cat.label"></option>
                            </template>
                        </select>
                        <select x-model="restockExpiryFilter" class="flex-shrink-0 w-36 sm:w-44 h-9 px-3 text-sm bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500/20 outline-none">
                            <option value="all">All Expiry</option>
                            <option value="none">No expiry</option>
                            <option value="soon">Expiring soon</option>
                            <option value="expired">Expired</option>
                        </select>
                    </div>
                </div>

                {{-- Table --}}
                <div class="flex-1 min-h-0 overflow-y-auto overflow-x-auto overscroll-contain inv-scroll">
                    <table class="w-full min-w-[1000px] text-sm">
                        <thead class="sticky top-0 z-10">
                            <tr class="border-b border-gray-100 bg-white">
                                <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Product</th>
                                <th class="text-left px-2 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Counter</th>
                                <th class="text-right px-2 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Current</th>
                                <th class="text-center px-2 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Reorder</th>
                                <th class="text-left px-2 py-2.5 text-xs font-semibold text-emerald-600 uppercase tracking-wider w-32">Buying Price</th>
                                <th class="text-left px-2 py-2.5 text-xs font-semibold text-emerald-600 uppercase tracking-wider w-32">Selling Price</th>
                                <th class="text-left px-2 py-2.5 text-xs font-semibold text-emerald-600 uppercase tracking-wider w-24 sm:w-28">Add Qty</th>
                                <th class="text-left px-2 py-2.5 text-xs font-semibold text-emerald-600 uppercase tracking-wider w-32 sm:w-40">Expiry Date</th>
                                <th class="text-right px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">New Stock</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <template x-for="p in restockList" :key="p.id">
                                <tr class="transition-colors" :class="restockQty(p.id) > 0 ? 'bg-emerald-50/40' : 'hover:bg-gray-50/50'">
                                    <td class="px-5 py-2.5">
                                        <div class="flex items-center gap-2.5">
                                            <span class="text-lg flex-shrink-0" x-text="p.cat === 'spirits' ? '🥃' : p.cat === 'beer' ? '🍺' : p.cat === 'wine' ? '🍷' : p.cat === 'softdrinks' ? '🥤' : p.cat === 'water' ? '💧' : p.cat === 'food' ? '🍔' : '📦'"></span>
                                            <div class="min-w-0">
                                                <p class="font-medium text-gray-900 truncate" x-text="p.name"></p>
                                                <p class="text-[11px] text-gray-400 truncate"><span x-text="p.sup"></span> &middot; <span x-text="p.unit"></span></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-2 py-2.5">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-600" x-text="p.counter"></span>
                                    </td>
                                    <td class="px-2 py-2.5 text-right whitespace-nowrap">
                                        <span class="font-medium" :class="restockQty(p.id) > 0 ? 'text-emerald-700' : p.stock === 0 ? 'text-red-600' : p.stock < p.reorder ? 'text-amber-600' : 'text-gray-700'" x-text="p.stock"></span>
                                        <span class="text-[11px] text-gray-400" x-text="p.unit"></span>
                                    </td>
                                    <td class="px-2 py-2.5 text-center text-gray-500" x-text="p.reorder"></td>
                                    <td class="px-2 py-2.5">
                                        <div class="relative">
                                            <span class="absolute left-2 top-1/2 -translate-y-1/2 text-[10px] text-gray-400 pointer-events-none"><span x-text="BmsMoney.symbol"></span></span>
                                            <input type="number" min="0" step="1" x-model="restockCosts[p.id]" class="w-full h-9 pl-7 pr-2 text-sm text-right bg-white border rounded-lg outline-none transition-all focus:ring-2" :class="restockQty(p.id) > 0 ? (restockCostChanged(p) ? 'border-blue-400 text-blue-700 focus:border-blue-500 focus:ring-blue-500/20' : 'border-emerald-400 focus:border-emerald-500 focus:ring-emerald-500/20') : 'border-gray-200 text-gray-500 focus:border-emerald-500 focus:ring-emerald-500/20'">
                                        </div>
                                        <p class="mt-1 text-[10px] truncate" :class="restockQty(p.id) > 0 ? (restockCostChanged(p) ? 'text-blue-600' : 'text-gray-400') : 'text-gray-300'">
                                            <span x-show="restockQty(p.id) > 0 && restockCostChanged(p)">was <span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(p.buyPrice)"></span></span>
                                            <span x-show="restockQty(p.id) > 0 && !restockCostChanged(p)">cost <span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(p.buyPrice)"></span></span>
                                        </p>
                                    </td>
                                    <td class="px-2 py-2.5">
                                        <div class="relative">
                                            <span class="absolute left-2 top-1/2 -translate-y-1/2 text-[10px] text-gray-400 pointer-events-none"><span x-text="BmsMoney.symbol"></span></span>
                                            <input type="number" min="0" step="1" x-model="restockSells[p.id]" class="w-full h-9 pl-7 pr-2 text-sm text-right bg-white border rounded-lg outline-none transition-all focus:ring-2" :class="restockQty(p.id) > 0 ? (restockSellChanged(p) ? 'border-blue-400 text-blue-700 focus:border-blue-500 focus:ring-blue-500/20' : 'border-emerald-400 focus:border-emerald-500 focus:ring-emerald-500/20') : 'border-gray-200 text-gray-500 focus:border-emerald-500 focus:ring-emerald-500/20'">
                                        </div>
                                        <p class="mt-1 text-[10px] truncate" :class="restockQty(p.id) > 0 ? (restockSellChanged(p) ? 'text-blue-600' : 'text-gray-400') : 'text-gray-300'">
                                            <span x-show="restockQty(p.id) > 0 && restockSellChanged(p)" x-cloak>was <span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(p.sellPrice)"></span></span>
                                        </p>
                                    </td>
                                    <td class="px-2 py-2.5">
                                        <div class="relative">
                                            <input type="number" min="0" step="1" x-model="restockLines[p.id]" placeholder="0" class="w-full h-9 pl-2 pr-7 text-sm text-right bg-white border rounded-lg outline-none transition-all focus:ring-2" :class="restockQty(p.id) > 0 ? 'border-emerald-400 focus:border-emerald-500 focus:ring-emerald-500/20' : 'border-gray-200 focus:border-emerald-500 focus:ring-emerald-500/20'">
                                            <span class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] text-gray-400 pointer-events-none" x-text="p.unit"></span>
                                        </div>
                                    </td>
                                    <td class="px-2 py-2.5">
                                        <input type="date" x-model="restockExpiry[p.id]" class="w-full h-9 px-2 text-xs bg-white border rounded-lg outline-none transition-all focus:ring-2" :class="restockQty(p.id) > 0 ? (restockHasExpiry(p) ? 'border-emerald-400 focus:border-emerald-500 focus:ring-emerald-500/20' : 'border-gray-200 text-gray-500 focus:border-emerald-500 focus:ring-emerald-500/20') : 'border-gray-200 text-gray-500 focus:border-emerald-500 focus:ring-emerald-500/20'">
                                        <template x-if="!restockHasExpiry(p) && productExpiry(p)">
                                            <p class="mt-1 text-[10px] font-medium text-gray-400">
                                                Current: <span x-text="formatExpiry(p)"></span>
                                            </p>
                                        </template>
                                        <p x-show="restockHasExpiry(p)" x-cloak class="mt-1 text-[10px] font-medium" :class="expiryStatus({ expiryDate: restockExpiry[p.id] }).tone === 'red' ? 'text-red-600' : expiryStatus({ expiryDate: restockExpiry[p.id] }).tone === 'amber' ? 'text-amber-600' : 'text-emerald-600'">
                                            <span x-text="expiryStatus({ expiryDate: restockExpiry[p.id] }).label"></span>
                                        </p>
                                        <p x-show="!restockHasExpiry(p)" class="mt-1 text-[10px] font-medium text-gray-300">
                                            <span x-show="!productExpiry(p)">Non-perishable</span>
                                        </p>
                                    </td>
                                    <td class="px-5 py-2.5 text-right whitespace-nowrap">
                                        <span class="font-bold" :class="restockQty(p.id) > 0 ? 'text-emerald-600' : 'text-gray-400'" x-text="restockNewQty(p)"></span>
                                        <span class="text-[11px] text-gray-400" x-text="p.unit"></span>
                                        <span class="block text-[10px] text-emerald-600" x-show="restockQty(p.id) > 0" x-text="'+ ' + BmsMoney.symbol + ' ' + formatMoney(restockLineValue(p))"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <div x-show="restockList.length === 0" class="py-12 text-center text-gray-400 text-sm">No products match your search.</div>
                </div>

                {{-- Footer --}}
                <div class="flex-shrink-0 px-5 py-3.5 bg-gray-50 border-t border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-x-5 gap-y-1 text-xs">
                            <span class="text-gray-500">Items: <span class="font-bold text-gray-900" x-text="restockLineCount"></span></span>
                            <span class="text-gray-500">Total units: <span class="font-bold text-gray-900" x-text="parseFloat(restockTotalUnits.toFixed(2))"></span></span>
                            <span class="text-gray-500">Purchase value: <span class="font-bold text-emerald-600"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(restockTotalValue)"></span></span></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="closeRestockModal()" class="flex-1 sm:flex-none px-4 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white transition-colors">Cancel</button>
                            <button @click="saveRestock()" :disabled="saving === 'restock'" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-emerald-600 text-sm font-semibold text-white hover:bg-emerald-700 transition-colors shadow-sm shadow-emerald-200 disabled:opacity-60">
                                <span x-show="saving === 'restock'" class="bms-progress-ring"></span>
                                <span x-show="saving !== 'restock'">@heroicon('o-check', 'w-4 h-4')</span>
                                <span x-text="saving === 'restock' ? 'Saving...' : 'Save Restock'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
    @endif

    @if (auth()->user()?->hasPermission('inventory', 'transfer'))
    {{-- Transfer Modal --}}
    <template x-teleport="body">
        <div x-show="showTransferModal"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             @click.away="showTransferModal = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showTransferModal = false"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg z-10 max-h-[90vh] overflow-y-auto"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 sticky top-0 bg-white z-10">
                    <h3 class="text-lg font-bold text-gray-900">Stock Transfer</h3>
                    <button @click="showTransferModal = false" class="p-1 text-gray-400 hover:text-gray-600">@heroicon('o-x-mark', 'w-5 h-5')</button>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">From Counter</label>
                        <select x-model="transferFrom" @change="loadTransferProducts()" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none">
                            <template x-for="counter in counters" :key="counter">
                                <option :value="counter" x-text="counter"></option>
                            </template>
                        </select>
                    </div>
                    <div class="flex justify-center">
                        <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center">
                            @heroicon('o-arrow-down', 'w-4 h-4 text-gray-500')
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">To Counter</label>
                        <select x-model="transferTo" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none">
                            <template x-for="counter in counters" :key="counter">
                                <option :value="counter" x-text="counter"></option>
                            </template>
                        </select>
                    </div>

                    {{-- Products to Transfer --}}
                    <div x-show="transferProducts.length > 0">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Select Products to Transfer</label>
                        <div class="border border-gray-200 rounded-xl overflow-hidden max-h-64 overflow-y-auto">
                            <table class="w-full text-xs">
                                <thead class="bg-gray-50 sticky top-0">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-semibold text-gray-600 w-8">
                                            <input type="checkbox" class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        </th>
                                        <th class="px-3 py-2 text-left font-semibold text-gray-600">Product</th>
                                        <th class="px-3 py-2 text-right font-semibold text-gray-600">Stock</th>
                                        <th class="px-3 py-2 text-right font-semibold text-gray-600">Transfer Qty</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="product in transferProducts" :key="product.id">
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2">
                                                <input type="checkbox" :value="product.id" x-model="selectedTransferProducts" class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            </td>
                                            <td class="px-3 py-2 text-gray-900" x-text="product.name"></td>
                                            <td class="px-3 py-2 text-right text-gray-900" x-text="product.stock + ' ' + product.unit"></td>
                                            <td class="px-3 py-2 text-right">
                                                <input type="number" min="0.001" step="0.001" :max="product.stock" x-model="transferQuantities[product.id]" class="w-20 h-7 px-2 text-xs border border-gray-300 rounded-lg focus:border-blue-500 outline-none">
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div x-show="transferFrom && transferProducts.length === 0" class="text-center py-4 text-sm text-gray-500">
                        No products available for transfer in this counter.
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex gap-2 sticky bottom-0">
                    <button @click="showTransferModal = false" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white">Cancel</button>
                    <button @click="saveTransfer()" :disabled="saving === 'transfer'" class="flex-1 py-2.5 rounded-xl bg-blue-600 text-sm font-semibold text-white hover:bg-blue-700 shadow-sm shadow-blue-200 disabled:opacity-60 inline-flex items-center justify-center gap-2">
                        <span x-show="saving === 'transfer'" class="bms-progress-ring"></span>
                        <span x-text="saving === 'transfer' ? 'Transferring...' : 'Transfer'"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>
    @endif

      {{-- Filter Modal --}}
      <template x-teleport="body">
          <div x-show="showFilterModal"
               x-cloak
               class="fixed inset-0 z-50 flex items-center justify-center p-4"
               @click.away="showFilterModal = false"
               x-transition:enter="transition ease-out duration-200"
               x-transition:enter-start="opacity-0"
               x-transition:enter-end="opacity-100">
              <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showFilterModal = false"></div>
              <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg z-10"
                   x-transition:enter="transition ease-out duration-200"
                   x-transition:enter-start="opacity-0 scale-95"
                   x-transition:enter-end="opacity-100 scale-100">
                  <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                      <h3 class="text-lg font-bold text-gray-900">Filter Products</h3>
                      <button @click="showFilterModal = false" class="p-1 text-gray-400 hover:text-gray-600">@heroicon('o-x-mark', 'w-5 h-5')</button>
                  </div>
                  <div class="px-6 py-5">
                      <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                          <button @click="filterDate = 'all'; customDateFrom = ''; customDateTo = ''" :class="filterDate === 'all' ? 'bg-blue-50 border-blue-200 text-blue-700' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'" class="px-3 py-2 rounded-lg border text-sm font-medium text-center transition-colors">All</button>
                          <button @click="filterDate = 'today'" :class="filterDate === 'today' ? 'bg-blue-50 border-blue-200 text-blue-700' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'" class="px-3 py-2 rounded-lg border text-sm font-medium text-center transition-colors">Today</button>
                          <button @click="filterDate = 'yesterday'" :class="filterDate === 'yesterday' ? 'bg-blue-50 border-blue-200 text-blue-700' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'" class="px-3 py-2 rounded-lg border text-sm font-medium text-center transition-colors">Yesterday</button>
                          <button @click="filterDate = 'thisWeek'" :class="filterDate === 'thisWeek' ? 'bg-blue-50 border-blue-200 text-blue-700' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'" class="px-3 py-2 rounded-lg border text-sm font-medium text-center transition-colors">This Week</button>
                          <button @click="filterDate = 'lastWeek'" :class="filterDate === 'lastWeek' ? 'bg-blue-50 border-blue-200 text-blue-700' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'" class="px-3 py-2 rounded-lg border text-sm font-medium text-center transition-colors">Last Week</button>
                          <button @click="filterDate = 'thisMonth'" :class="filterDate === 'thisMonth' ? 'bg-blue-50 border-blue-200 text-blue-700' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'" class="px-3 py-2 rounded-lg border text-sm font-medium text-center transition-colors">This Month</button>
                          <button @click="filterDate = 'lastMonth'" :class="filterDate === 'lastMonth' ? 'bg-blue-50 border-blue-200 text-blue-700' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'" class="px-3 py-2 rounded-lg border text-sm font-medium text-center transition-colors">Last Month</button>
                          <button @click="filterDate = 'lastThreeMonths'" :class="filterDate === 'lastThreeMonths' ? 'bg-blue-50 border-blue-200 text-blue-700' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'" class="px-3 py-2 rounded-lg border text-sm font-medium text-center transition-colors">Last 3 Months</button>
                          <button @click="filterDate = 'thisYear'" :class="filterDate === 'thisYear' ? 'bg-blue-50 border-blue-200 text-blue-700' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'" class="px-3 py-2 rounded-lg border text-sm font-medium text-center transition-colors">This Year</button>
                          <button @click="filterDate = 'custom'" :class="filterDate === 'custom' ? 'bg-blue-50 border-blue-200 text-blue-700' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'" class="px-3 py-2 rounded-lg border text-sm font-medium text-center transition-colors col-span-2 sm:col-span-3">Custom Range</button>
                      </div>
                      <template x-if="filterDate === 'custom'">
                          <div class="flex flex-col sm:flex-row items-center gap-2 mt-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                              <input type="date" x-model="customDateFrom" class="w-full sm:flex-1 h-9 px-3 text-sm bg-white border border-gray-300 rounded-lg focus:border-blue-500 outline-none">
                              <span class="text-xs text-gray-400">to</span>
                              <input type="date" x-model="customDateTo" class="w-full sm:flex-1 h-9 px-3 text-sm bg-white border border-gray-300 rounded-lg focus:border-blue-500 outline-none">
                          </div>
                      </template>
                  </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex gap-2">
                    <button @click="showFilterModal = false" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white">Cancel</button>
                    <button @click="showFilterModal = false" class="flex-1 py-2.5 rounded-xl bg-blue-600 text-sm font-semibold text-white hover:bg-blue-700 shadow-sm shadow-blue-200">Apply</button>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection
