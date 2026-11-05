@extends('layouts.master', ['activeNav' => $activeNav ?? 'kitchen'])

@section('title', 'Kitchen Management')

@php
    $kitchenUser = auth()->user();
    $canRawAdd = $kitchenUser?->hasPermission('kitchen', 'rawAdd') ?? false;
    $canRawEdit = $kitchenUser?->hasPermission('kitchen', 'rawEdit') ?? false;
    $canRawDelete = $kitchenUser?->hasPermission('kitchen', 'rawDelete') ?? false;
    $canRawValue = $kitchenUser?->hasPermission('kitchen', 'rawValue') ?? false;
    $canRecipeAdd = $kitchenUser?->hasPermission('kitchen', 'recipeAdd') ?? false;
    $canRecipeEdit = $kitchenUser?->hasPermission('kitchen', 'recipeEdit') ?? false;
    $canRecipeDelete = $kitchenUser?->hasPermission('kitchen', 'recipeDelete') ?? false;
    $canFoodAdd = $kitchenUser?->hasPermission('kitchen', 'foodAdd') ?? false;
    $canFoodEdit = $kitchenUser?->hasPermission('kitchen', 'foodEdit') ?? false;
    $canFoodDelete = $kitchenUser?->hasPermission('kitchen', 'foodDelete') ?? false;
@endphp

@push('head')
<style>
.bms-progress-ring { width: 1rem; height: 1rem; border: 2px solid currentColor; border-right-color: transparent; border-radius: 9999px; animation: bms-progress-spin .7s linear infinite; }
@keyframes bms-progress-spin { to { transform: rotate(360deg); } }
</style>
<script>
window.kitchenData = function (initialQuery, scope) {
    var q = initialQuery || '';
    var startTab = 'overview';
    if (scope === 'materials') { startTab = 'raw-materials'; }
    if (scope === 'recipes') { startTab = 'recipes'; }
    if (scope === 'food') { startTab = 'food-items'; }

    return {
        activeTab: startTab,
        searchQuery: q,
        canAddRecipe: @json($canRecipeAdd ?? false),
        canEditRecipe: @json($canRecipeEdit ?? false),
        rawMaterials: window.BmsTrash.readCollection('bms.kitchen.rawMaterials', [
            { id: 1, name: 'Chicken Breast', sku: 'RM-001', category: 'Meat', unit: 'kg', baseUnit: 'kg', conversion: 1, currentQty: 25.5, alertLevel: 10, costPerUnit: 8000, avgCost: 8000, lastCost: 8000, supplier: 'Supplier C', batchNo: 'BATCH-1001', expiryDate: '2026-10-28', active: true, image: '🍗' },
            { id: 2, name: 'Beef', sku: 'RM-002', category: 'Meat', unit: 'kg', baseUnit: 'kg', conversion: 1, currentQty: 18.2, alertLevel: 8, costPerUnit: 12000, avgCost: 12000, lastCost: 12000, supplier: 'Supplier C', batchNo: 'BATCH-1002', expiryDate: '2026-10-27', active: true, image: '🥩' },
            { id: 3, name: 'Fish Fillet', sku: 'RM-003', category: 'Meat', unit: 'kg', baseUnit: 'kg', conversion: 1, currentQty: 12.0, alertLevel: 5, costPerUnit: 15000, avgCost: 15000, lastCost: 15000, supplier: 'Supplier C', batchNo: 'BATCH-1003', expiryDate: '2026-10-26', active: true, image: '🐟' },
            { id: 4, name: 'Prawns', sku: 'RM-004', category: 'Meat', unit: 'kg', baseUnit: 'kg', conversion: 1, currentQty: 3.5, alertLevel: 5, costPerUnit: 25000, avgCost: 25000, lastCost: 25000, supplier: 'Supplier C', batchNo: 'BATCH-1004', expiryDate: '2026-10-25', active: true, image: '🦐' },
            { id: 5, name: 'Cooking Oil', sku: 'RM-005', category: 'Oils', unit: 'L', baseUnit: 'L', conversion: 1, currentQty: 25.0, alertLevel: 10, costPerUnit: 3500, avgCost: 3500, lastCost: 3500, supplier: 'Supplier G', batchNo: 'BATCH-1005', expiryDate: '2027-06-30', active: true, image: '🛢️' },
            { id: 6, name: 'Tomatoes', sku: 'RM-006', category: 'Vegetables', unit: 'kg', baseUnit: 'kg', conversion: 1, currentQty: 18.5, alertLevel: 10, costPerUnit: 2000, avgCost: 2000, lastCost: 2000, supplier: 'Supplier G', batchNo: 'BATCH-1006', expiryDate: '2026-10-27', active: true, image: '🍅' },
            { id: 7, name: 'Onions', sku: 'RM-007', category: 'Vegetables', unit: 'kg', baseUnit: 'kg', conversion: 1, currentQty: 22.0, alertLevel: 10, costPerUnit: 1800, avgCost: 1800, lastCost: 1800, supplier: 'Supplier G', batchNo: 'BATCH-1007', expiryDate: '2026-10-30', active: true, image: '🧅' },
            { id: 8, name: 'Potatoes', sku: 'RM-008', category: 'Vegetables', unit: 'kg', baseUnit: 'kg', conversion: 1, currentQty: 40.0, alertLevel: 15, costPerUnit: 1200, avgCost: 1200, lastCost: 1200, supplier: 'Supplier G', batchNo: 'BATCH-1008', expiryDate: '2026-11-05', active: true, image: '🥔' },
            { id: 9, name: 'Lettuce', sku: 'RM-009', category: 'Vegetables', unit: 'kg', baseUnit: 'kg', conversion: 1, currentQty: 4.5, alertLevel: 3, costPerUnit: 2500, avgCost: 2500, lastCost: 2500, supplier: 'Supplier G', batchNo: 'BATCH-1009', expiryDate: '2026-10-25', active: true, image: '🥬' },
            { id: 10, name: 'Cabbage', sku: 'RM-010', category: 'Vegetables', unit: 'kg', baseUnit: 'kg', conversion: 1, currentQty: 15.0, alertLevel: 8, costPerUnit: 1500, avgCost: 1500, lastCost: 1500, supplier: 'Supplier G', batchNo: 'BATCH-1010', expiryDate: '2026-10-29', active: true, image: '🥬' },
            { id: 11, name: 'Rice', sku: 'RM-011', category: 'Grains', unit: 'kg', baseUnit: 'kg', conversion: 1, currentQty: 50.0, alertLevel: 20, costPerUnit: 2000, avgCost: 2000, lastCost: 2000, supplier: 'Supplier H', batchNo: 'BATCH-1011', expiryDate: '2027-12-31', active: true, image: '🍚' },
            { id: 12, name: 'Flour', sku: 'RM-012', category: 'Grains', unit: 'kg', baseUnit: 'kg', conversion: 1, currentQty: 30.0, alertLevel: 10, costPerUnit: 1500, avgCost: 1500, lastCost: 1500, supplier: 'Supplier H', batchNo: 'BATCH-1012', expiryDate: '2027-10-31', active: true, image: '🌾' },
            { id: 13, name: 'Salt', sku: 'RM-013', category: 'Seasonings', unit: 'kg', baseUnit: 'kg', conversion: 1, currentQty: 10.0, alertLevel: 2, costPerUnit: 800, avgCost: 800, lastCost: 800, supplier: 'Supplier H', batchNo: 'BATCH-1013', expiryDate: '2028-12-31', active: true, image: '🧂' },
            { id: 14, name: 'Black Pepper', sku: 'RM-014', category: 'Seasonings', unit: 'kg', baseUnit: 'kg', conversion: 1, currentQty: 2.5, alertLevel: 1, costPerUnit: 15000, avgCost: 15000, lastCost: 15000, supplier: 'Supplier H', batchNo: 'BATCH-1014', expiryDate: '2027-08-31', active: true, image: '🌶️' },
            { id: 15, name: 'Spices Mix', sku: 'RM-015', category: 'Seasonings', unit: 'kg', baseUnit: 'kg', conversion: 1, currentQty: 5.0, alertLevel: 2, costPerUnit: 12000, avgCost: 12000, lastCost: 12000, supplier: 'Supplier H', batchNo: 'BATCH-1015', expiryDate: '2027-09-30', active: true, image: '🧂' },
            { id: 16, name: 'Vinegar', sku: 'RM-016', category: 'Condiments', unit: 'L', baseUnit: 'L', conversion: 1, currentQty: 5.0, alertLevel: 2, costPerUnit: 3000, avgCost: 3000, lastCost: 3000, supplier: 'Supplier H', batchNo: 'BATCH-1016', expiryDate: '2027-12-31', active: true, image: '🍶' },
            { id: 17, name: 'Tomato Sauce', sku: 'RM-017', category: 'Condiments', unit: 'kg', baseUnit: 'kg', conversion: 1, currentQty: 8.0, alertLevel: 3, costPerUnit: 4500, avgCost: 4500, lastCost: 4500, supplier: 'Supplier H', batchNo: 'BATCH-1017', expiryDate: '2027-06-30', active: true, image: '🍅' },
            { id: 18, name: 'Mayonnaise', sku: 'RM-018', category: 'Condiments', unit: 'kg', baseUnit: 'kg', conversion: 1, currentQty: 6.0, alertLevel: 3, costPerUnit: 6500, avgCost: 6500, lastCost: 6500, supplier: 'Supplier H', batchNo: 'BATCH-1018', expiryDate: '2027-05-31', active: true, image: '🥄' },
            { id: 19, name: 'Milk', sku: 'RM-019', category: 'Dairy', unit: 'L', baseUnit: 'L', conversion: 1, currentQty: 10.0, alertLevel: 5, costPerUnit: 2500, avgCost: 2500, lastCost: 2500, supplier: 'Supplier I', batchNo: 'BATCH-1019', expiryDate: '2026-10-26', active: true, image: '🥛' },
            { id: 20, name: 'Cheese', sku: 'RM-020', category: 'Dairy', unit: 'kg', baseUnit: 'kg', conversion: 1, currentQty: 7.0, alertLevel: 3, costPerUnit: 18000, avgCost: 18000, lastCost: 18000, supplier: 'Supplier I', batchNo: 'BATCH-1020', expiryDate: '2026-10-28', active: true, image: '🧀' }
        ]),
        rmSearchQuery: scope === 'materials' ? q : '',
        rmCategoryFilter: 'all',
        rmStatusFilter: 'all',
        showAddRawMaterial: false,
        showRawMaterialDetail: false,
        selectedRawMaterial: null,
        newRawMaterial: { name: '', sku: '', category: 'Meat', unit: 'kg', currentQty: '', alertLevel: '', costPerUnit: '', supplier: '', expiryDate: '' },
        showEditRawMaterial: false,
        editingRawMaterial: null,
        editRawMaterial: { name: '', sku: '', category: 'Meat', unit: 'kg', currentQty: '', alertLevel: '', costPerUnit: '', supplier: '', expiryDate: '' },
        showDeleteRawMaterial: false,
        rawMaterialToDelete: null,
        showImportMaterials: false,
        importMaterialsFile: null,
        importMaterialsSaving: false,
        importMaterialsError: '',
        importMaterialRows: [],
        importMaterialSelectedRows: [],
        importMaterialSummary: { total: 0, valid: 0, invalid: 0 },
        importMaterialCategoryId: '',
        importMaterialUnit: window.BmsUnits.read()[0] || 'units',
        importMaterialCategories: @json($kitchenRawMaterialCategories->map(fn ($category) => ['id' => $category->id, 'name' => $category->name])->values()),
        materialSaving: '',
        rawMaterialCategories: @json($kitchenRawMaterialCategories->pluck('name')),
        rawMaterialUnits: window.BmsUnits.read(),

        init() {
            var self = this;
            this.rawMaterials = @json($kitchenRawMaterials);
            this.rawMaterialCategories = @json($kitchenRawMaterialCategories->pluck('name'));
            this.recipes = @json($kitchenRecipes);
            this.foodItems = @json($kitchenFoodItems);
            this.rawMaterials.forEach(function (m) {
                if (m.unit && self.rawMaterialUnits.indexOf(m.unit) === -1) self.rawMaterialUnits.push(m.unit);
            });
            this.recipes.forEach(function (r) {
                if (r.outputUnit && self.recipeUnits.indexOf(r.outputUnit) === -1) self.recipeUnits.push(r.outputUnit);
            });
        },
        csrfToken() {
            var meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        },
        apiError(error, fallback) {
            var data = error.response && error.response.data;
            if (data && data.errors) return Object.keys(data.errors).map(function (key) { return data.errors[key][0]; }).join(' ');
            return data && data.message ? data.message : fallback;
        },
        materialCategoryId(name) {
            var categories = @json($kitchenRawMaterialCategories->map(fn ($category) => ['id' => $category->id, 'name' => $category->name]));
            var category = categories.find(function (item) { return item.name === name; });
            return category ? category.id : null;
        },
        applyMaterial(material) {
            var index = this.rawMaterials.findIndex(function (item) { return item.id === material.id; });
            if (index < 0) this.rawMaterials.push(material);
            else this.rawMaterials.splice(index, 1, material);
            if (this.selectedRawMaterial && this.selectedRawMaterial.id === material.id) this.selectedRawMaterial = material;
        },

        // ========== RAW MATERIAL SUMMARY ==========
        get rawMaterialCount() { return this.rawMaterials.length; },
        get rawMaterialStockValue() {
            return this.rawMaterials.reduce(function (sum, m) { return sum + ((parseFloat(m.currentQty) || 0) * (parseFloat(m.avgCost) || m.costPerUnit || 0)); }, 0);
        },
        get rawMaterialLowStockCount() { return this.rawMaterials.filter(function (m) { return m.currentQty <= m.alertLevel; }).length; },
        get rawMaterialCategoryCount() {
            var set = {};
            this.rawMaterials.forEach(function (m) { set[m.category] = true; });
            return Object.keys(set).length;
        },

        // ========== RESTOCK ==========
        showRestockModal: false,
        restockLines: {},
        restockCosts: {},
        restockExpiry: {},
        restockSearch: '',
        restockCategory: 'all',
        get restockList() { return this.filterRmList(this.restockSearch, this.restockCategory); },
        restockQty(id) { return parseFloat(this.restockLines[id]) || 0; },
        restockCurrentCost(m) { return parseFloat(m.avgCost) || parseFloat(m.costPerUnit) || 0; },
        restockCost(m) {
            var v = this.restockCosts[m.id];
            if (v === undefined || v === null || v === '') return this.restockCurrentCost(m);
            return parseFloat(v) || 0;
        },
        restockExpiryFor(m) {
            var v = this.restockExpiry[m.id];
            return (v === undefined || v === null || v === '') ? (m.expiryDate || '') : v;
        },
        restockExpiryState(m) {
            var val = this.restockExpiryFor(m);
            if (!val) return { label: '', input: 'border-gray-200 focus:border-emerald-500 focus:ring-emerald-500/20', hint: 'text-gray-400' };
            var diff = (new Date(val + 'T00:00:00') - new Date()) / (1000 * 60 * 60 * 24);
            if (diff < 0) return { label: 'Expired', input: 'border-red-400 text-red-700 focus:border-red-500 focus:ring-red-500/20', hint: 'text-red-600' };
            if (diff <= 3) return { label: 'Expiring soon', input: 'border-amber-400 text-amber-700 focus:border-amber-500 focus:ring-amber-500/20', hint: 'text-amber-600' };
            return { label: '', input: 'border-gray-200 focus:border-emerald-500 focus:ring-emerald-500/20', hint: 'text-gray-400' };
        },
        restockNewQty(m) { return (parseFloat(m.currentQty) || 0) + this.restockQty(m.id); },
        restockLineValue(m) { return this.restockQty(m.id) * this.restockCost(m); },
        restockCostChanged(m) { return this.restockQty(m.id) > 0 && this.restockCost(m) !== this.restockCurrentCost(m); },
        get restockLineCount() {
            var self = this;
            return this.rawMaterials.filter(function (m) { return self.restockQty(m.id) > 0; }).length;
        },
        get restockTotalValue() {
            var self = this;
            return this.rawMaterials.reduce(function (sum, m) { return sum + (self.restockQty(m.id) > 0 ? self.restockLineValue(m) : 0); }, 0);
        },
        get restockTotalUnits() {
            var self = this;
            return this.rawMaterials.filter(function (m) { return self.restockQty(m.id) > 0; })
                .reduce(function (sum, m) { return sum + self.restockQty(m.id); }, 0);
        },
        get restockHasIssues() {
            var self = this;
            return this.rawMaterials.some(function (m) {
                if (self.restockQty(m.id) <= 0) return false;
                return !!self.restockExpiryState(m).label;
            });
        },
        openRestockModal() {
            var costs = {}, expiry = {};
            this.rawMaterials.forEach(function (m) {
                costs[m.id] = parseFloat(m.avgCost) || parseFloat(m.costPerUnit) || 0;
                expiry[m.id] = m.expiryDate || '';
            });
            this.restockLines = {};
            this.restockCosts = costs;
            this.restockExpiry = expiry;
            this.restockSearch = '';
            this.restockCategory = 'all';
            this.showRestockModal = true;
        },
        closeRestockModal() {
            this.showRestockModal = false;
            this.restockLines = {};
            this.restockCosts = {};
            this.restockExpiry = {};
        },
        saveRestock() {
            if (this.restockLineCount === 0) { this.flash('Enter a quantity for at least one material.', 'error'); return; }
            if (this.materialSaving) return;
            var self = this;
            var expired = this.rawMaterials.filter(function (m) { return self.restockQty(m.id) > 0 && self.restockExpiryState(m).label === 'Expired'; });
            if (expired.length) { this.flash('Cannot restock expired stock: ' + expired.map(function (m) { return m.name; }).join(', ') + '.', 'error'); return; }
            var items = this.rawMaterials.filter(function (material) { return self.restockQty(material.id) > 0; }).map(function (material) {
                return {
                    material_id: material.id,
                    quantity: self.restockQty(material.id),
                    unit_cost: self.restockCost(material),
                    expiry_date: self.restockExpiryFor(material),
                    batch_no: material.batchNo || '',
                };
            });
            this.materialSaving = 'restock';
            window.axios.post(@json(route('kitchen.materials.restock')), { items: items }, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function (response) {
                    (response.data.materials || []).forEach(function (material) { self.applyMaterial(material); });
                    self.closeRestockModal();
                    self.flash('Stock restocked successfully.', 'success');
                })
                .catch(function (error) { self.flash(self.apiError(error, 'Could not restock raw materials.'), 'error'); })
                .then(function () { self.materialSaving = ''; });
        },

        // ========== ADJUST ==========
        // Mirrors Inventory → Bulk Stock Adjustment: "Balancing" sets the
        // stock to the counted quantity entered, while "Bad" and "Expired"
        // deduct that quantity from the current stock. Only the deducting
        // reasons can exceed the stock on hand.
        showAdjustModal: false,
        adjustLines: {},
        adjustReasons: {},
        adjustSearch: '',
        adjustCategory: 'all',
        adjustReasonOptions: [
            { id: 'balancing', label: 'Balancing', tone: 'blue', hint: 'Sets stock to the quantity entered' },
            { id: 'bad', label: 'Bad', tone: 'amber', hint: 'Deducts the quantity from current stock' },
            { id: 'expired', label: 'Expired', tone: 'red', hint: 'Deducts the quantity from current stock' },
        ],
        get adjustList() { return this.filterRmList(this.adjustSearch, this.adjustCategory); },
        adjustReasonFor(id) {
            var rid = this.adjustReasons[id] || 'balancing';
            return this.adjustReasonOptions.find(function (r) { return r.id === rid; }) || this.adjustReasonOptions[0];
        },
        adjustQty(id) { return parseFloat(this.adjustLines[id]) || 0; },
        adjustIsBalance(id) { return this.adjustReasonFor(id).id === 'balancing'; },
        adjustNewQty(m) {
            var q = this.adjustQty(m.id);
            var next = this.adjustIsBalance(m.id) ? q : (parseFloat(m.currentQty) || 0) - q;
            return Math.round(Math.max(next, 0) * 10000) / 10000;
        },
        adjustOverflow(m) {
            return !this.adjustIsBalance(m.id) && this.adjustQty(m.id) > (parseFloat(m.currentQty) || 0);
        },
        get adjustLineCount() {
            var self = this;
            return this.rawMaterials.filter(function (m) { return self.adjustQty(m.id) > 0; }).length;
        },
        get adjustHasOverflow() {
            var self = this;
            return this.rawMaterials.some(function (m) { return self.adjustQty(m.id) > 0 && self.adjustOverflow(m); });
        },
        get adjustTotalQty() {
            var self = this;
            return this.rawMaterials.filter(function (m) { return self.adjustQty(m.id) > 0; })
                .reduce(function (sum, m) { return sum + self.adjustQty(m.id); }, 0);
        },
        get adjustValueImpact() {
            var self = this;
            return this.rawMaterials.reduce(function (sum, m) {
                if (self.adjustQty(m.id) <= 0) return sum;
                return sum + ((self.adjustNewQty(m) - (parseFloat(m.currentQty) || 0)) * (parseFloat(m.costPerUnit) || 0));
            }, 0);
        },
        get adjustReasonSummary() {
            var self = this;
            var used = {};
            this.rawMaterials.forEach(function (m) {
                if (self.adjustQty(m.id) <= 0) return;
                var r = self.adjustReasonFor(m.id);
                used[r.id] = (used[r.id] || 0) + 1;
            });
            return this.adjustReasonOptions
                .filter(function (r) { return used[r.id]; })
                .map(function (r) { return { id: r.id, label: r.label, tone: r.tone, count: used[r.id] }; });
        },
        openAdjustModal() {
            this.adjustLines = {};
            this.adjustReasons = {};
            this.adjustSearch = '';
            this.adjustCategory = 'all';
            this.showAdjustModal = true;
        },
        closeAdjustModal() {
            this.showAdjustModal = false;
            this.adjustLines = {};
            this.adjustReasons = {};
        },
        saveAdjust() {
            if (this.adjustLineCount === 0) { this.flash('Enter a quantity for at least one material.', 'error'); return; }
            if (this.adjustHasOverflow) { this.flash('Adjustment cannot be greater than the current stock.', 'error'); return; }
            var self = this;
            if (this.materialSaving) return;
            var items = this.rawMaterials.filter(function (material) { return self.adjustQty(material.id) > 0; }).map(function (material) {
                return { material_id: material.id, quantity: self.adjustQty(material.id), reason: self.adjustReasonFor(material.id).id };
            });
            this.materialSaving = 'adjust';
            window.axios.post(@json(route('kitchen.materials.adjust')), { items: items }, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function (response) {
                    (response.data.materials || []).forEach(function (material) { self.applyMaterial(material); });
                    self.closeAdjustModal();
                    self.flash(response.data.message || 'Raw material stock adjusted.', 'success');
                })
                .catch(function (error) { self.flash(self.apiError(error, 'Could not adjust raw material stock.'), 'error'); })
                .then(function () { self.materialSaving = ''; });
        },

        // ========== SHARED HELPERS ==========
        filterRmList(search, category) {
            var q = (search || '').toLowerCase().trim();
            return this.rawMaterials.filter(function (m) {
                if (category && category !== 'all' && m.category !== category) return false;
                if (q && m.name.toLowerCase().indexOf(q) === -1 && m.sku.toLowerCase().indexOf(q) === -1) return false;
                return true;
            });
        },
        rmToneClass(tone) {
            var map = {
                blue: 'bg-blue-50 text-blue-700 ring-blue-600/20 border-blue-200',
                amber: 'bg-amber-50 text-amber-700 ring-amber-600/20 border-amber-200',
                red: 'bg-red-50 text-red-700 ring-red-600/20 border-red-200',
            };
            return map[tone] || map.blue;
        },
        flash(message, type) {
            this.toast = { show: true, message: message, type: type || 'success' };
            var self = this;
            clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(function () { self.toast.show = false; }, 3200);
        },
        toast: { show: false, message: '', type: 'success' },
        _toastTimer: null,

        get filteredRawMaterials() {
            var q = this.rmSearchQuery.toLowerCase().trim();
            var cat = this.rmCategoryFilter;
            var status = this.rmStatusFilter;
            return this.rawMaterials.filter(function (m) {
                if (cat !== 'all' && m.category !== cat) return false;
                if (q) {
                    var haystack = [m.name, m.sku, m.category, m.supplier, m.batchNo]
                        .map(function (v) { return String(v || '').toLowerCase(); })
                        .join(' ');
                    if (!haystack.includes(q)) return false;
                }
                if (status === 'low' && m.currentQty > m.alertLevel) return false;
                if (status === 'good' && m.currentQty <= m.alertLevel) return false;
                return true;
            });
        },
        getLowStockCount() { return this.rawMaterials.filter(function(m){ return m.currentQty <= m.alertLevel; }).length; },
        getExpiringCount() {
            var now = new Date();
            var three = new Date(now.getTime() + 3*24*60*60*1000);
            return this.rawMaterials.filter(function(m){
                var exp = new Date(m.expiryDate);
                return exp <= three && exp >= now;
            }).length;
        },
        rmStockStatus(m) {
            if (m.currentQty <= 0) return { label: 'Out of Stock', color: 'bg-red-50 text-red-700 ring-1 ring-red-600/20' };
            if (m.currentQty <= m.alertLevel) return { label: 'Low Stock', color: 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20' };
            return { label: 'In Stock', color: 'bg-green-50 text-green-700 ring-1 ring-green-600/20' };
        },
        rmExpiryStatus(m) {
            var now = new Date();
            var exp = new Date(m.expiryDate);
            var diff = (exp - now) / (1000*60*60*24);
            if (diff < 0) return { label: 'Expired', color: 'bg-red-50 text-red-700 ring-1 ring-red-600/20' };
            if (diff <= 3) return { label: 'Expiring Soon', color: 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20' };
            return { label: 'Good', color: 'bg-green-50 text-green-700 ring-1 ring-green-600/20' };
        },
        openRawMaterialDetail(m) { this.selectedRawMaterial = m; this.showRawMaterialDetail = true; },
        openAddRawMaterial() {
            this.newRawMaterial = { name: '', sku: '', category: this.rawMaterialCategories[0] || '', unit: 'kg', currentQty: '', alertLevel: '', costPerUnit: '', supplier: '', expiryDate: '' };
            this.showAddRawMaterial = true;
        },
        addRawMaterial() {
            if (!this.newRawMaterial.name) { this.flash('Enter a name for the raw material.', 'error'); return; }
            if (this.materialSaving) return;
            var self = this;
            var payload = {
                name: this.newRawMaterial.name,
                sku: this.newRawMaterial.sku || null,
                category_id: this.materialCategoryId(this.newRawMaterial.category),
                unit: this.newRawMaterial.unit,
                quantity: this.newRawMaterial.currentQty || 0,
                alert_level: this.newRawMaterial.alertLevel || 0,
                cost_per_unit: this.newRawMaterial.costPerUnit || 0,
                supplier: this.newRawMaterial.supplier || null,
                expiry_date: this.newRawMaterial.expiryDate || null,
            };
            if (!payload.category_id) { this.flash('Create a raw material category in Settings first.', 'error'); return; }
            this.materialSaving = 'create';
            window.axios.post(@json(route('kitchen.materials.store')), payload, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function (response) {
                    self.applyMaterial(response.data.material);
                    self.showAddRawMaterial = false;
                    self.flash('Raw material added successfully.', 'success');
                })
                .catch(function (error) { self.flash(self.apiError(error, 'Could not add the raw material.'), 'error'); })
                .then(function () { self.materialSaving = ''; });
        },
        openEditRawMaterial(m) {
            this.editingRawMaterial = m;
            if (m) {
                this.editRawMaterial = {
                    name: m.name, sku: m.sku, category: m.category, unit: m.unit,
                    currentQty: m.currentQty, alertLevel: m.alertLevel,
                    costPerUnit: m.costPerUnit, supplier: m.supplier, expiryDate: m.expiryDate
                };
            }
            this.showEditRawMaterial = true;
        },
        saveEditRawMaterial() {
            if (!this.editingRawMaterial || !this.editRawMaterial.name) return;
            if (this.materialSaving) return;
            var self = this;
            var input = this.editRawMaterial;
            var payload = {
                name: input.name,
                sku: input.sku || null,
                category_id: this.materialCategoryId(input.category),
                unit: input.unit,
                alert_level: input.alertLevel,
                cost_per_unit: input.costPerUnit,
                supplier: input.supplier || null,
                expiry_date: input.expiryDate || null,
            };
            this.materialSaving = 'edit';
            window.axios.put(@json(url('/kitchen/raw-materials')) + '/' + this.editingRawMaterial.id, payload, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function (response) {
                    self.applyMaterial(response.data.material);
                    self.showEditRawMaterial = false;
                    self.flash('Raw material updated successfully.', 'success');
                })
                .catch(function (error) { self.flash(self.apiError(error, 'Could not update the raw material.'), 'error'); })
                .then(function () { self.materialSaving = ''; });
        },
        openDeleteRawMaterial(m) { this.rawMaterialToDelete = m; this.showDeleteRawMaterial = true; },
        confirmDeleteRawMaterial() {
            if (!this.rawMaterialToDelete || this.materialSaving) return;
            var self = this;
            var id = this.rawMaterialToDelete.id;
            this.materialSaving = 'delete';
            window.axios.delete(@json(url('/kitchen/raw-materials')) + '/' + id, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function (response) {
                    self.rawMaterials = self.rawMaterials.filter(function (material) { return material.id !== id; });
                    self.showDeleteRawMaterial = false;
                    self.rawMaterialToDelete = null;
                    self.flash(response.data.message || 'Raw material deleted.', 'success');
                })
                .catch(function (error) { self.flash(self.apiError(error, 'Could not delete the raw material.'), 'error'); })
                .then(function () { self.materialSaving = ''; });
        },
        importRawMaterials(event) {
            var file = event.target.files[0];
            if (!file) return;
            var form = new FormData();
            form.append('file', file);
            var self = this;
            this.importMaterialsError = '';
            this.importMaterialsFile = file;
            this.importMaterialsSaving = true;
            window.axios.post(@json(route('kitchen.materials.preview')), form, {
                headers: { 'X-CSRF-TOKEN': self.csrfToken(), 'Content-Type': 'multipart/form-data' },
            })
                .then(function (response) {
                    self.importMaterialRows = response.data.rows || [];
                    self.importMaterialSummary = response.data.summary || { total: 0, valid: 0, invalid: 0 };
                    self.importMaterialSelectedRows = self.importMaterialRows
                        .filter(function (row) { return row.valid && !row.is_sample; })
                        .map(function (row) { return row.row; });
                    self.importMaterialCategoryId = self.importMaterialCategories.length ? self.importMaterialCategories[0].id : '';
                    self.showImportMaterials = true;
                })
                .catch(function (error) { self.flash(self.apiError(error, 'Could not preview raw materials.'), 'error'); })
                .then(function () {
                    self.importMaterialsSaving = false;
                    event.target.value = '';
                });
        },
        confirmImportRawMaterials() {
            if (!this.importMaterialsFile || !this.importMaterialCategoryId || !this.importMaterialUnit || this.importMaterialsSaving) return;
            if (!this.importMaterialSelectedRows.length) {
                this.importMaterialsError = 'Select at least one valid row to import.';
                return;
            }
            var form = new FormData();
            form.append('file', this.importMaterialsFile);
            form.append('category_id', this.importMaterialCategoryId);
            form.append('unit', this.importMaterialUnit);
            this.importMaterialRows.forEach(function (row) {
                if (this.importMaterialSelectedRows.indexOf(row.row) === -1) form.append('exclude_rows[]', row.row);
            }, this);
            var self = this;
            this.importMaterialsSaving = true;
            this.importMaterialsError = '';
            window.axios.post(@json(route('kitchen.materials.import')), form, {
                headers: { 'X-CSRF-TOKEN': self.csrfToken(), 'Content-Type': 'multipart/form-data' },
            })
                .then(function (response) {
                    (response.data.materials || []).forEach(function (material) { self.applyMaterial(material); });
                    self.showImportMaterials = false;
                    self.importMaterialsFile = null;
                    self.flash(response.data.message || 'Raw materials imported.', 'success');
                })
                .catch(function (error) {
                    self.importMaterialsError = self.apiError(error, 'Could not import raw materials.');
                })
                .then(function () { self.importMaterialsSaving = false; });
        },
        recipes: window.BmsTrash.readCollection('bms.kitchen.recipes', [
            {
                id: 1, name: 'Chicken Wings', code: 'REC-001', category: 'BBQ', type: 'Menu Item',
                outputQty: 1, outputUnit: 'serving', prepTime: 15, cookTime: 12, version: 1, active: true,
                instructions: 'Marinate chicken wings in spices. Deep fry until golden brown. Serve hot with sauce.',
                ingredients: [
                    { materialId: 1, name: 'Chicken Breast', qty: 0.250, unit: 'kg', consumptionQty: 0.250, consumptionUnit: 'kg', unitCost: 8000, wastePct: 8, totalCost: 2160 },
                    { materialId: 15, name: 'Spices Mix', qty: 0.030, unit: 'kg', consumptionQty: 0.030, consumptionUnit: 'kg', unitCost: 12000, wastePct: 0, totalCost: 360 },
                    { materialId: 5, name: 'Cooking Oil', qty: 0.050, unit: 'L', consumptionQty: 0.050, consumptionUnit: 'L', unitCost: 3500, wastePct: 0, totalCost: 175 },
                    { materialId: 16, name: 'Vinegar', qty: 20, unit: 'ml', consumptionQty: 20, consumptionUnit: 'ml', unitCost: 3, wastePct: 0, totalCost: 60 },
                ],
                sellingPrice: 8500
            },
            {
                id: 2, name: 'Beef Burger', code: 'REC-002', category: 'Fast Food', type: 'Menu Item',
                outputQty: 1, outputUnit: 'serving', prepTime: 10, cookTime: 8, version: 1, active: true,
                instructions: 'Grill beef patty. Assemble with lettuce, tomato, onion, cheese and mayonnaise in bun.',
                ingredients: [
                    { materialId: 2, name: 'Beef', qty: 0.200, unit: 'kg', consumptionQty: 0.200, consumptionUnit: 'kg', unitCost: 12000, wastePct: 5, totalCost: 2520 },
                    { materialId: 12, name: 'Flour', qty: 0.080, unit: 'kg', consumptionQty: 0.080, consumptionUnit: 'kg', unitCost: 1500, wastePct: 0, totalCost: 120 },
                    { materialId: 9, name: 'Lettuce', qty: 0.030, unit: 'kg', consumptionQty: 0.030, consumptionUnit: 'kg', unitCost: 2500, wastePct: 0, totalCost: 75 },
                    { materialId: 6, name: 'Tomatoes', qty: 0.050, unit: 'kg', consumptionQty: 0.050, consumptionUnit: 'kg', unitCost: 2000, wastePct: 5, totalCost: 105 },
                    { materialId: 7, name: 'Onions', qty: 0.030, unit: 'kg', consumptionQty: 0.030, consumptionUnit: 'kg', unitCost: 1800, wastePct: 5, totalCost: 57 },
                    { materialId: 20, name: 'Cheese', qty: 0.030, unit: 'kg', consumptionQty: 0.030, consumptionUnit: 'kg', unitCost: 18000, wastePct: 0, totalCost: 540 },
                    { materialId: 18, name: 'Mayonnaise', qty: 0.020, unit: 'kg', consumptionQty: 0.020, consumptionUnit: 'kg', unitCost: 6500, wastePct: 0, totalCost: 130 },
                    { materialId: 5, name: 'Cooking Oil', qty: 0.015, unit: 'L', consumptionQty: 0.015, consumptionUnit: 'L', unitCost: 3500, wastePct: 0, totalCost: 53 },
                ],
                sellingPrice: 8500
            },
            {
                id: 3, name: 'Grilled Chicken', code: 'REC-003', category: 'Main Course', type: 'Menu Item',
                outputQty: 1, outputUnit: 'serving', prepTime: 10, cookTime: 25, version: 1, active: true,
                instructions: 'Marinate chicken and grill to perfection. Serve with sides.',
                ingredients: [
                    { materialId: 1, name: 'Chicken Breast', qty: 0.350, unit: 'kg', consumptionQty: 0.350, consumptionUnit: 'kg', unitCost: 8000, wastePct: 5, totalCost: 2940 },
                    { materialId: 15, name: 'Spices Mix', qty: 0.030, unit: 'kg', consumptionQty: 0.030, consumptionUnit: 'kg', unitCost: 12000, wastePct: 0, totalCost: 360 },
                    { materialId: 5, name: 'Cooking Oil', qty: 0.030, unit: 'L', consumptionQty: 0.030, consumptionUnit: 'L', unitCost: 3500, wastePct: 0, totalCost: 105 },
                ],
                sellingPrice: 12000
            },
            {
                id: 4, name: 'Fish & Chips', code: 'REC-004', category: 'Main Course', type: 'Menu Item',
                outputQty: 1, outputUnit: 'serving', prepTime: 10, cookTime: 15, version: 1, active: true,
                instructions: 'Coat fish in batter. Deep fry with chips.',
                ingredients: [
                    { materialId: 3, name: 'Fish Fillet', qty: 0.250, unit: 'kg', consumptionQty: 0.250, consumptionUnit: 'kg', unitCost: 15000, wastePct: 5, totalCost: 3938 },
                    { materialId: 8, name: 'Potatoes', qty: 0.300, unit: 'kg', consumptionQty: 0.300, consumptionUnit: 'kg', unitCost: 1200, wastePct: 10, totalCost: 396 },
                    { materialId: 12, name: 'Flour', qty: 0.100, unit: 'kg', consumptionQty: 0.100, consumptionUnit: 'kg', unitCost: 1500, wastePct: 0, totalCost: 150 },
                    { materialId: 5, name: 'Cooking Oil', qty: 0.080, unit: 'L', consumptionQty: 0.080, consumptionUnit: 'L', unitCost: 3500, wastePct: 0, totalCost: 280 },
                ],
                sellingPrice: 11000
            },
            {
                id: 5, name: 'French Fries', code: 'REC-005', category: 'Sides', type: 'Menu Item',
                outputQty: 1, outputUnit: 'serving', prepTime: 10, cookTime: 8, version: 1, active: true,
                instructions: 'Cut potatoes into strips. Double fry for crispiness. Season with salt.',
                ingredients: [
                    { materialId: 8, name: 'Potatoes', qty: 0.200, unit: 'kg', consumptionQty: 0.200, consumptionUnit: 'kg', unitCost: 1200, wastePct: 10, totalCost: 264 },
                    { materialId: 5, name: 'Cooking Oil', qty: 0.030, unit: 'L', consumptionQty: 0.030, consumptionUnit: 'L', unitCost: 3500, wastePct: 0, totalCost: 105 },
                    { materialId: 13, name: 'Salt', qty: 0.005, unit: 'kg', consumptionQty: 0.005, consumptionUnit: 'kg', unitCost: 800, wastePct: 0, totalCost: 4 },
                ],
                sellingPrice: 2500
            },
            {
                id: 6, name: 'Onion Rings', code: 'REC-006', category: 'Sides', type: 'Menu Item',
                outputQty: 1, outputUnit: 'serving', prepTime: 10, cookTime: 6, version: 1, active: true,
                instructions: 'Coat onion rings in batter. Deep fry until golden.',
                ingredients: [
                    { materialId: 7, name: 'Onions', qty: 0.150, unit: 'kg', consumptionQty: 0.150, consumptionUnit: 'kg', unitCost: 1800, wastePct: 0, totalCost: 270 },
                    { materialId: 12, name: 'Flour', qty: 0.050, unit: 'kg', consumptionQty: 0.050, consumptionUnit: 'kg', unitCost: 1500, wastePct: 0, totalCost: 75 },
                    { materialId: 5, name: 'Cooking Oil', qty: 0.030, unit: 'L', consumptionQty: 0.030, consumptionUnit: 'L', unitCost: 3500, wastePct: 0, totalCost: 105 },
                ],
                sellingPrice: 3000
            }
        ]),
        recipeSearchQuery: scope === 'recipes' ? q : '',
        recipeCategoryFilter: 'all',
        showAddRecipe: false,
        showDeleteRecipe: false,
        recipeToDelete: null,
        recipeFormMode: 'add',
        editingRecipeId: null,
        showRecipeDetail: false,
        selectedRecipe: null,
        newRecipe: { name: '', code: '', category: 'Fast Food', type: 'Menu Item', outputQty: '1', outputUnit: 'pcs', instructions: '' },
        newRecipeIngredients: [],
        recipeCategories: ['Fast Food', 'BBQ', 'Main Course', 'Sides', 'Desserts', 'Beverages'],
        recipeUnits: window.BmsUnits.read(),
        get filteredRecipes() {
            var q = this.recipeSearchQuery.toLowerCase().trim();
            var cat = this.recipeCategoryFilter;
            return this.recipes.filter(function (r) {
                if (cat !== 'all' && r.category !== cat) return false;
                if (q) {
                    var haystack = [r.name, r.code, r.category, r.type]
                        .map(function (v) { return String(v || '').toLowerCase(); })
                        .join(' ');
                    if (!haystack.includes(q)) return false;
                }
                return true;
            });
        },
        recipeTotalCost(r) {
            return r.ingredients.reduce(function (sum, i) { return sum + (parseFloat(i.totalCost) || 0); }, 0);
        },
        recipeCostPerServing(r) {
            var output = parseFloat(r.outputQty) || 1;
            return this.recipeTotalCost(r) / output;
        },
        addRecipeIngredient() {
            this.newRecipeIngredients.push({ materialId: '', name: '', qty: '', unit: 'kg', unitCost: 0, totalCost: 0 });
        },
        removeRecipeIngredient(idx) { this.newRecipeIngredients.splice(idx, 1); },
        normalizeRecipeIngredient(i) {
            var qty = parseFloat(i.qty) || 0;
            var unitCost = parseFloat(i.unitCost) || 0;
            var materialId = parseInt(i.materialId, 10);
            return {
                materialId: isNaN(materialId) ? '' : materialId,
                name: i.name || '',
                qty: qty,
                unit: i.unit || 'kg',
                consumptionQty: parseFloat(i.consumptionQty) || qty,
                consumptionUnit: i.consumptionUnit || i.unit || 'kg',
                unitCost: unitCost,
                wastePct: parseFloat(i.wastePct) || 0,
                totalCost: (i.totalCost === undefined || i.totalCost === null || i.totalCost === '')
                    ? (qty * unitCost)
                    : (parseFloat(i.totalCost) || 0),
            };
        },
        updateRecipeIngredientCost(ing) {
            var mat = this.rawMaterials.find(function(m){ return m.id == ing.materialId; });
            if (mat) {
                ing.materialId = mat.id;
                ing.name = mat.name;
                ing.unitCost = parseFloat(mat.costPerUnit) || parseFloat(mat.avgCost) || 0;
                ing.unit = mat.unit;
            }
            ing.totalCost = (parseFloat(ing.qty) || 0) * (parseFloat(ing.unitCost) || 0);
        },
        openAddRecipe() {
            this.recipeFormMode = 'add';
            this.editingRecipeId = null;
            this.newRecipe = { name: '', code: '', category: 'Fast Food', type: 'Menu Item', outputQty: '1', outputUnit: 'pcs', instructions: '' };
            this.newRecipeIngredients = [];
            this.showAddRecipe = true;
        },
        openEditRecipe(r) {
            this.recipeFormMode = 'edit';
            this.editingRecipeId = r.id;
            this.newRecipe = { name: r.name, code: r.code, category: r.category, type: r.type || 'Menu Item', outputQty: r.outputQty, outputUnit: r.outputUnit, instructions: r.instructions || '' };
            var self = this;
            this.newRecipeIngredients = r.ingredients.map(function (ing) { return self.normalizeRecipeIngredient(ing); });
            this.showAddRecipe = true;
        },
        saveEditRecipe() {
            if (!this.newRecipe.name || this.editingRecipeId == null) { this.flash('Enter a recipe name.', 'error'); return; }
            this.persistRecipe(this.editingRecipeId);
        },
        openDeleteRecipe(r) { this.recipeToDelete = r; this.showDeleteRecipe = true; },
        confirmDeleteRecipe() {
            if (!this.recipeToDelete || this.materialSaving) return;
            var self = this;
            var id = this.recipeToDelete.id;
            this.materialSaving = 'recipe-delete';
            window.axios.delete(@json(url('/kitchen/recipes')) + '/' + id, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function () {
                    self.recipes = self.recipes.filter(function (recipe) { return recipe.id !== id; });
                    self.foodItems.forEach(function (item) { item.recipeIds = item.recipeIds.filter(function (recipeId) { return recipeId !== id; }); });
                    self.recipeToDelete = null;
                    self.showDeleteRecipe = false;
                    self.flash('Recipe deleted.', 'success');
                })
                .catch(function (error) { self.flash(self.apiError(error, 'Could not delete the recipe.'), 'error'); })
                .then(function () { self.materialSaving = ''; });
        },
        addRecipe() {
            if (!this.newRecipe.name) { this.flash('Enter a recipe name.', 'error'); return; }
            this.persistRecipe(null);
        },
        persistRecipe(id) {
            if (this.materialSaving) return;
            var self = this;
            var payload = {
                name: this.newRecipe.name,
                code: this.newRecipe.code || null,
                category: this.newRecipe.category,
                type: this.newRecipe.type || 'Menu Item',
                output_quantity: this.newRecipe.outputQty || 1,
                output_unit: this.newRecipe.outputUnit || 'serving',
                instructions: this.newRecipe.instructions || null,
                ingredients: this.newRecipeIngredients.map(function (ingredient) {
                    return {
                        material_id: ingredient.materialId,
                        quantity: ingredient.qty,
                        unit: ingredient.unit || 'kg',
                        waste_percent: ingredient.wastePct || 0,
                    };
                }),
            };
            this.materialSaving = 'recipe';
            var request = id
                ? window.axios.put(@json(url('/kitchen/recipes')) + '/' + id, payload, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                : window.axios.post(@json(route('kitchen.recipes.store')), payload, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } });
            request.then(function (response) {
                var recipe = response.data.recipe;
                var index = self.recipes.findIndex(function (item) { return item.id === recipe.id; });
                if (index < 0) self.recipes.push(recipe);
                else self.recipes.splice(index, 1, recipe);
                self.showAddRecipe = false;
                self.flash(id ? 'Recipe updated.' : 'Recipe created.', 'success');
            })
                .catch(function (error) { self.flash(self.apiError(error, 'Could not save the recipe.'), 'error'); })
                .then(function () { self.materialSaving = ''; });
        },
        kitchenSettings: {
            lowStockThreshold: 20, expiryWarningDays: 3,
            defaultWasteReason: 'Preparation Loss', enableBatchTracking: true, enableFIFO: true,
        },
        recentActivity: @json($recentActivity),
        topUsedIngredients: @json($topUsedIngredients),
        get topUsedIngredientMax() {
            return Math.max.apply(null, this.topUsedIngredients.map(function (item) { return item.used; }).concat([1]));
        },
        formatMoney(v) { return Math.round(v).toLocaleString(); },
        formatQty(q, u) { return q + ' ' + u; },

        // ========== FOOD ITEMS (Menu Items) ==========
        foodItems: window.BmsTrash.readCollection('bms.kitchen.foodItems', [
            { id: 1, name: 'Chicken Wings', category: 'food', price: 8500, image: '🍗', recipeIds: [1], available: true, instructions: 'Marinate and deep fry', expiryDate: '' },
            { id: 2, name: 'Beef Burger', category: 'food', price: 8500, image: '🍔', recipeIds: [2], available: true, instructions: 'Grill and assemble', expiryDate: '' },
            { id: 3, name: 'Grilled Chicken', category: 'food', price: 12000, image: '🍖', recipeIds: [3], available: true, instructions: 'Grill to perfection', expiryDate: '' },
            { id: 4, name: 'Fish & Chips', category: 'food', price: 11000, image: '🐟', recipeIds: [4], available: true, instructions: 'Batter and fry', expiryDate: '' },
            { id: 5, name: 'French Fries', category: 'sides', price: 2500, image: '🍟', recipeIds: [5], available: true, instructions: 'Double fry', expiryDate: '' },
            { id: 6, name: 'Onion Rings', category: 'sides', price: 3000, image: '🧅', recipeIds: [6], available: true, instructions: 'Batter and fry', expiryDate: '' }
        ]),

        foodSearchQuery: scope === 'food' ? q : '',
        foodCategoryFilter: 'all',
        showAddFoodItem: false,
        showEditFoodItem: false,
        editingFoodItem: null,
        showDeleteFoodItem: false,
        foodItemToDelete: null,
        foodCategories: [
            { id: 'food', label: 'Food', icon: '🍲' },
            { id: 'sides', label: 'Sides', icon: '🍟' },
            { id: 'beverages', label: 'Beverages', icon: '🥤' },
            { id: 'desserts', label: 'Desserts', icon: '🍰' },
        ],
        newFoodItem: { name: '', category: 'food', price: '', image: '', recipeIds: [], instructions: '', expiryDate: '', available: true },
        editFoodItem: { name: '', category: 'food', price: '', image: '', recipeIds: [], instructions: '', expiryDate: '', available: true },

        get filteredFoodItems() {
            var q = this.foodSearchQuery.toLowerCase().trim();
            var cat = this.foodCategoryFilter;
            return this.foodItems.filter(function (item) {
                if (cat !== 'all' && item.category !== cat) return false;
                if (q) {
                    var haystack = [item.name, item.category]
                        .map(function (v) { return String(v || '').toLowerCase(); })
                        .join(' ');
                    if (!haystack.includes(q)) return false;
                }
                return true;
            });
        },

        getFoodItemCount() { return this.foodItems.length; },
        isFoodImage(src) {
            return typeof src === 'string' && (
                src.indexOf('data:') === 0 || src.indexOf('http://') === 0 ||
                src.indexOf('https://') === 0 || src.indexOf('blob:') === 0 ||
                src.charAt(0) === '/'
            );
        },

        getFoodRecipes(item) {
            var ids = (item.recipeIds || []).map(Number);
            return this.recipes.filter(function(r){ return ids.indexOf(Number(r.id)) > -1; });
        },
        getFoodRecipe(item) {
            var list = this.getFoodRecipes(item);
            return list.length ? list[0] : null;
        },

        // ========== UNIT CONVERSION ==========
        unitConversions: {
            'kg->g': 1000, 'g->kg': 0.001,
            'L->ml': 1000, 'ml->L': 0.001,
            'bottle->ml': 750, 'ml->bottle': 0.00133,
            'tray->pcs': 30, 'pcs->tray': 0.033,
            'carton->pcs': 30, 'pcs->carton': 0.033,
        },

        convertUnit(qty, fromUnit, toUnit) {
            if (fromUnit === toUnit) return qty;
            var key = fromUnit + '->' + toUnit;
            var factor = this.unitConversions[key];
            return factor ? qty * factor : qty;
        },

        formatQtyUnit(qty, unit) {
            if (unit === 'kg' && qty < 1) return Math.round(qty * 1000) + ' g';
            if (unit === 'L' && qty < 1) return Math.round(qty * 1000) + ' ml';
            return parseFloat(qty.toFixed(2)) + ' ' + unit;
        },

        // ========== INGREDIENT CONSUMPTION ==========
        consumptionLog: [],

        consumeIngredients(foodItem, qty) {
            var recipes = this.getFoodRecipes(foodItem);
            if (!recipes.length) return { success: false, errors: ['No recipe linked'] };

            var self = this;
            var consumed = [];
            var errors = [];

            recipes.forEach(function(recipe) {
                recipe.ingredients.forEach(function(ing) {
                    var material = self.rawMaterials.find(function(m){ return m.id === ing.materialId; });
                    if (!material) { errors.push(ing.name + ' not found'); return; }

                    var perServing = parseFloat(ing.consumptionQty) || parseFloat(ing.qty) || 0;
                    var wasteMult = 1 + ((parseFloat(ing.wastePct) || 0) / 100);
                    var totalNeeded = perServing * qty * wasteMult;

                    if (material.currentQty < totalNeeded) {
                        errors.push(ing.name + ' insufficient (need ' + self.formatQtyUnit(totalNeeded, material.unit) + ', have ' + self.formatQtyUnit(material.currentQty, material.unit) + ')');
                        return;
                    }

                    material.currentQty = Math.round((material.currentQty - totalNeeded) * 10000) / 10000;
                    consumed.push({ materialId: material.id, name: material.name, deducted: totalNeeded, unit: material.unit, remaining: material.currentQty });
                });
            });

            if (errors.length === 0) {
                self.consumptionLog.unshift({
                    id: Date.now(), foodItem: foodItem.name, qty: qty, items: consumed,
                    timestamp: new Date().toISOString(), type: 'pos-sale'
                });
            }

            return { success: errors.length === 0, consumed: consumed, errors: errors };
        },

        canFulfillOrder(foodItem, qty) {
            var recipes = this.getFoodRecipes(foodItem);
            if (!recipes.length) return { canFulfill: false, missing: [{ name: 'No recipe', needed: 0, available: 0, unit: '' }] };

            var self = this;
            var missing = [];
            recipes.forEach(function(recipe) {
                recipe.ingredients.forEach(function(ing) {
                    var material = self.rawMaterials.find(function(m){ return m.id === ing.materialId; });
                    if (!material) { missing.push({ name: ing.name, needed: 0, available: 0, unit: ing.unit }); return; }
                    var perServing = parseFloat(ing.consumptionQty) || parseFloat(ing.qty) || 0;
                    var wasteMult = 1 + ((parseFloat(ing.wastePct) || 0) / 100);
                    var totalNeeded = perServing * qty * wasteMult;
                    if (material.currentQty < totalNeeded) {
                        missing.push({ name: material.name, needed: totalNeeded, available: material.currentQty, unit: material.unit });
                    }
                });
            });
            return { canFulfill: missing.length === 0, missing: missing };
        },

        openAddFoodItem() {
            this.newFoodItem = { name: '', category: 'food', price: '', image: '', recipeIds: [], instructions: '', expiryDate: '', available: true };
            this.showAddFoodItem = true;
        },
        addFoodItem() {
            if (!this.newFoodItem.name) { this.flash('Enter a name for the food item.', 'error'); return; }
            this.persistFoodItem(null, this.newFoodItem);
        },
        openEditFoodItem(item) {
            this.editingFoodItem = item;
            this.editFoodItem = { name: item.name, category: item.category, price: item.price, image: item.image || '', recipeIds: (item.recipeIds || []).slice(), instructions: item.instructions, expiryDate: item.expiryDate || '', available: item.available };
            this.showEditFoodItem = true;
        },
        saveEditFoodItem() {
            if (!this.editingFoodItem) return;
            this.persistFoodItem(this.editingFoodItem.id, this.editFoodItem);
        },
        persistFoodItem(id, form) {
            if (this.materialSaving) return;
            var self = this;
            var payload = {
                name: form.name,
                category: form.category,
                price: form.price || 0,
                image: form.image || '',
                recipe_ids: (form.recipeIds || []).map(Number).filter(Boolean),
                instructions: form.instructions || '',
                expiry_date: form.expiryDate || null,
                available: !!form.available,
            };
            this.materialSaving = 'food-item';
            var request = id
                ? window.axios.put(@json(url('/kitchen/food-items')) + '/' + id, payload, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                : window.axios.post(@json(route('kitchen.food-items.store')), payload, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } });
            request.then(function (response) {
                var item = response.data.foodItem;
                var index = self.foodItems.findIndex(function (current) { return current.id === item.id; });
                if (index < 0) self.foodItems.push(item);
                else self.foodItems.splice(index, 1, item);
                self.showAddFoodItem = false;
                self.showEditFoodItem = false;
                self.flash(id ? 'Food item updated.' : 'Food item added.', 'success');
            })
                .catch(function (error) { self.flash(self.apiError(error, 'Could not save the food item.'), 'error'); })
                .then(function () { self.materialSaving = ''; });
        },
        toggleFoodAvailability(item) {
            var form = {
                name: item.name, category: item.category, price: item.price, image: item.image,
                recipeIds: item.recipeIds, instructions: item.instructions, expiryDate: item.expiryDate,
                available: !item.available,
            };
            this.persistFoodItem(item.id, form);
        },
        openDeleteFoodItem(item) { this.foodItemToDelete = item; this.showDeleteFoodItem = true; },
        closeDeleteFoodItem() { this.showDeleteFoodItem = false; this.foodItemToDelete = null; },
        confirmDeleteFoodItem() {
            if (!this.foodItemToDelete || this.materialSaving) return;
            var self = this;
            var id = this.foodItemToDelete.id;
            this.materialSaving = 'food-delete';
            window.axios.delete(@json(url('/kitchen/food-items')) + '/' + id, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function () {
                    self.foodItems = self.foodItems.filter(function (item) { return item.id !== id; });
                    self.closeDeleteFoodItem();
                    self.flash('Food item deleted.', 'success');
                })
                .catch(function (error) { self.flash(self.apiError(error, 'Could not delete the food item.'), 'error'); })
                .then(function () { self.materialSaving = ''; });
        },
        handleFoodImageUpload(e, mode) {
            var file = e.target.files[0];
            if (!file) return;
            if (file.size > 2 * 1024 * 1024) { alert('Image must be under 2MB'); e.target.value = ''; return; }
            var reader = new FileReader();
            var self = this;
            reader.onload = function(ev) {
                if (mode === 'new') self.newFoodItem.image = ev.target.result;
                else self.editFoodItem.image = ev.target.result;
            };
            reader.readAsDataURL(file);
        },
    };
};
</script>
@endpush
@section('content')
<div x-data="kitchenData(@js(is_string(request('q')) ? request('q') : ''), @js(is_string(request('scope')) ? request('scope') : ''))" class="pb-6">
    {{-- Toast --}}
    <div x-show="toast.show" x-cloak class="fixed z-[60] bottom-4 right-4 left-4 sm:left-auto sm:w-auto sm:min-w-[20rem]">
        <div class="flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg border bg-white"
             :class="toast.type === 'error' ? 'border-red-200' : 'border-emerald-200'"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                 :class="toast.type === 'error' ? 'bg-red-50' : 'bg-emerald-50'">
                <span :class="toast.type === 'error' ? 'text-red-600' : 'text-emerald-600'">
                    <template x-if="toast.type === 'error'">@heroicon('o-exclamation-triangle', 'w-5 h-5')</template>
                    <template x-if="toast.type !== 'error'">@heroicon('o-check-circle', 'w-5 h-5')</template>
                </span>
            </div>
            <p class="text-sm font-medium flex-1" :class="toast.type === 'error' ? 'text-red-700' : 'text-gray-900'" x-text="toast.message"></p>
            <button @click="toast.show = false" class="text-gray-400 hover:text-gray-600">@heroicon('o-x-mark', 'w-4 h-4')</button>
        </div>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Kitchen Management</h1>
            <p class="mt-1 text-sm text-gray-500">Manage raw materials, recipes, food items, and kitchen operations.</p>
        </div>
    </div>

    @php
        $kitchenTabs = [
            ['id' => 'overview', 'label' => 'Overview', 'icon' => 'o-squares-2x2'],
            ['id' => 'food-items', 'label' => 'Food Items', 'icon' => 'o-clipboard-document-list'],
            ['id' => 'raw-materials', 'label' => 'Raw Materials', 'icon' => 'o-cube'],
            ['id' => 'recipes', 'label' => 'Recipes', 'icon' => 'o-book-open'],
        ];
    @endphp
    <div class="flex items-center gap-1 overflow-x-auto no-scrollbar mb-6 -mx-4 px-4 sm:mx-0 sm:px-0">
        @foreach($kitchenTabs as $kt)
            <button @click="activeTab = '{{ $kt['id'] }}'"
                    :class="activeTab === '{{ $kt['id'] }}' ? 'bg-blue-600 text-white shadow-sm shadow-blue-200' : 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-200'"
                    class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-medium whitespace-nowrap transition-all duration-150 flex-shrink-0">
                @heroicon($kt['icon'], 'w-4 h-4')
                <span>{{ $kt['label'] }}</span>
            </button>
        @endforeach
    </div>

    {{-- OVERVIEW TAB --}}
    <div x-show="activeTab === 'overview'" x-cloak>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                        @heroicon('o-cube', 'w-5 h-5 text-blue-600')
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Raw Materials</p>
                        <p class="text-lg font-bold text-gray-900" x-text="rawMaterials.length"></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center">
                        @heroicon('o-exclamation-triangle', 'w-5 h-5 text-amber-600')
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Low Stock</p>
                        <p class="text-lg font-bold text-gray-900" x-text="getLowStockCount()"></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center">
                        @heroicon('o-clock', 'w-5 h-5 text-red-600')
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Expiring Soon</p>
                        <p class="text-lg font-bold text-gray-900" x-text="getExpiringCount()"></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-green-50 flex items-center justify-center">
                        @heroicon('o-book-open', 'w-5 h-5 text-green-600')
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Active Recipes</p>
                        <p class="text-lg font-bold text-gray-900" x-text="recipes.filter(function(r){ return r.active; }).length"></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-900">Low Stock Ingredients</h3>
                    <button @click="activeTab = 'raw-materials'" class="text-xs text-blue-600 hover:text-blue-700 font-medium">View All</button>
                </div>
                <div class="divide-y divide-gray-50 max-h-72 overflow-y-auto">
                    <template x-for="m in rawMaterials.filter(function(m){ return m.currentQty <= m.alertLevel; })" :key="m.id">
                        <div class="px-5 py-3 flex items-center justify-between hover:bg-gray-50/40 transition-colors">
                            <div class="flex items-center gap-3">
                                <span class="text-xl" x-text="m.image"></span>
                                <div>
                                    <p class="text-sm font-medium text-gray-900" x-text="m.name"></p>
                                    <p class="text-xs text-gray-500">Alert: <span x-text="m.alertLevel + ' ' + m.unit"></span></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold text-red-600" x-text="m.currentQty + ' ' + m.unit"></p>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-red-50 text-red-700 ring-1 ring-red-600/20">Low</span>
                            </div>
                        </div>
                    </template>
                    <template x-if="rawMaterials.filter(function(m){ return m.currentQty <= m.alertLevel; }).length === 0">
                        <div class="px-5 py-8 text-center"><p class="text-sm text-gray-500">All ingredients are well stocked</p></div>
                    </template>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-900">Expiring Soon</h3>
                    <button @click="activeTab = 'raw-materials'" class="text-xs text-blue-600 hover:text-blue-700 font-medium">View All</button>
                </div>
                <div class="divide-y divide-gray-50 max-h-72 overflow-y-auto">
                    <template x-for="m in rawMaterials.filter(function(m){ var exp=new Date(m.expiryDate); var now=new Date(); var d=(exp-now)/(1000*60*60*24); return d <= 3 && d >= 0; })" :key="m.id">
                        <div class="px-5 py-3 flex items-center justify-between hover:bg-gray-50/40 transition-colors">
                            <div class="flex items-center gap-3">
                                <span class="text-xl" x-text="m.image"></span>
                                <div>
                                    <p class="text-sm font-medium text-gray-900" x-text="m.name"></p>
                                    <p class="text-xs text-gray-500">Batch: <span x-text="m.batchNo"></span></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-xs font-medium text-amber-600" x-text="m.expiryDate"></p>
                                <p class="text-xs text-gray-500" x-text="m.currentQty + ' ' + m.unit + ' remaining'"></p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-900">Recent Kitchen Activity</h3>
                </div>
                <div class="divide-y divide-gray-50 max-h-72 overflow-y-auto">
                    <template x-for="a in recentActivity" :key="a.id">
                        <div class="px-5 py-3 flex items-start gap-3 hover:bg-gray-50/40 transition-colors">
                            <span class="mt-1 w-2 h-2 rounded-full flex-shrink-0" :class="a.color"></span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-700" x-text="a.text"></p>
                                <p class="text-xs text-gray-400 mt-0.5" x-text="a.time"></p>
                            </div>
                        </div>
                    </template>
                    <p x-show="!recentActivity.length" class="px-5 py-8 text-center text-sm text-gray-400">
                        No kitchen activity yet.
                    </p>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-900">Most Used Ingredients in Recipes</h3>
                </div>
                <div class="p-5">
                    <div class="space-y-3">
                        <template x-for="(item, idx) in topUsedIngredients" :key="item.name + item.unit">
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-medium text-gray-500 w-4" x-text="idx + 1"></span>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-sm font-medium text-gray-900" x-text="item.name"></span>
                                        <span class="text-sm text-gray-600" x-text="item.used + ' ' + item.unit"></span>
                                    </div>
                                    <div class="w-full bg-gray-100 rounded-full h-1.5">
                                        <div class="bg-blue-500 h-1.5 rounded-full" :style="'width:' + Math.min((item.used / topUsedIngredientMax) * 100, 100) + '%'"></div>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <template x-if="topUsedIngredients.length === 0">
                            <p class="px-5 py-8 text-center text-sm text-gray-500">Add recipe ingredients to see usage totals.</p>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Food Items Summary --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm lg:col-span-2">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-900">Food Items</h3>
                    <button @click="activeTab = 'food-items'" class="text-xs text-blue-600 hover:text-blue-700 font-medium">View All</button>
                </div>
                <div class="divide-y divide-gray-50 max-h-80 overflow-y-auto">
                    <template x-for="item in foodItems.filter(function(i){ return i.available; })" :key="item.id">
                        <div class="px-5 py-3 flex items-center justify-between hover:bg-gray-50/40 transition-colors">
                            <div class="flex items-center gap-3">
                                <template x-if="item.image && isFoodImage(item.image)"><img :src="item.image" class="w-10 h-10 rounded-lg object-cover flex-shrink-0"></template>
                                <template x-if="item.image && !isFoodImage(item.image)"><div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0 text-xl" x-text="item.image"></div></template>
                                <template x-if="!item.image"><div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">@heroicon('o-photo', 'w-4 h-4 text-gray-400')</div></template>
                                <div>
                                    <p class="text-sm font-medium text-gray-900" x-text="item.name"></p>
                                    <p class="text-[10px] text-gray-400">Recipes: <span x-text="getFoodRecipes(item).length ? getFoodRecipes(item).length + ' linked' : 'None'"></span></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold text-gray-900" x-show="item.price > 0"><span x-text="BmsMoney.symbol"></span> <span x-text="item.price.toLocaleString()"></span></p>
                                <span class="text-xs text-gray-400" x-show="item.price === 0">No price</span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    {{-- FOOD ITEMS TAB --}}
    <div x-show="activeTab === 'food-items'" x-cloak>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
            <div>
                <h2 class="text-lg font-bold text-gray-900">Food Items</h2>
                <p class="mt-1 text-sm text-gray-500">Manage menu items with recipes, pricing, and availability.</p>
            </div>
            @if ($canFoodAdd)
            <div class="flex items-center gap-3 mt-4 sm:mt-0">
                <button @click="openAddFoodItem()" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-blue-600 text-white text-sm font-semibold rounded-xl hover:bg-blue-700 transition-colors shadow-sm shadow-blue-200">
                    @heroicon('o-plus', 'w-4 h-4')
                    Add Food Item
                </button>
            </div>
            @endif
        </div>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-2 gap-3 mb-6">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">@heroicon('o-clipboard-document-list', 'w-5 h-5 text-blue-600')</div>
                    <div><p class="text-xs text-gray-500">Total Items</p><p class="text-lg font-bold text-gray-900" x-text="getFoodItemCount()"></p></div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-green-50 flex items-center justify-center">@heroicon('o-book-open', 'w-5 h-5 text-green-600')</div>
                    <div><p class="text-xs text-gray-500">With Recipes</p><p class="text-lg font-bold text-gray-900" x-text="foodItems.filter(function(i){ return i.recipeIds && i.recipeIds.length; }).length"></p></div>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
            <div class="relative flex-1 max-w-xs">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" x-model.debounce.300ms="foodSearchQuery" placeholder="Search food items..." class="w-full h-9 pl-9 pr-4 text-sm bg-white border border-gray-200 rounded-lg focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
            </div>
            <div class="flex items-center gap-2 overflow-x-auto no-scrollbar">
                <button @click="foodCategoryFilter = 'all'" :class="foodCategoryFilter === 'all' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all whitespace-nowrap">All</button>
                <template x-for="cat in foodCategories" :key="cat.id">
                    <button @click="foodCategoryFilter = cat.id" :class="foodCategoryFilter === cat.id ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all whitespace-nowrap flex items-center gap-1">
                        <span x-text="cat.icon"></span><span x-text="cat.label"></span>
                    </button>
                </template>
            </div>
        </div>

        {{-- Food Items Table --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/50">
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Item</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Category</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Recipe</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Price</th>
                            <th class="text-center px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Available</th>
                            @if ($canFoodEdit || $canFoodDelete)
                            <th class="text-center px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template x-for="(item, index) in filteredFoodItems" :key="item.id">
                            <tr class="hover:bg-gray-50/40 transition-colors">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2.5">
                                        <template x-if="item.image && isFoodImage(item.image)"><img :src="item.image" class="w-10 h-10 rounded-lg object-cover flex-shrink-0"></template>
                                        <template x-if="item.image && !isFoodImage(item.image)"><div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0 text-xl" x-text="item.image"></div></template>
                                        <template x-if="!item.image"><div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">@heroicon('o-photo', 'w-4 h-4 text-gray-400')</div></template>
                                        <div>
                                            <p class="font-medium text-gray-900" x-text="item.name"></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-600" x-text="foodCategories.find(function(c){return c.id === item.category})?.label || item.category"></span>
                                </td>
                                <td class="px-5 py-3">
                                    <template x-if="getFoodRecipes(item).length > 0">
                                        <div class="flex flex-col gap-0.5">
                                            <template x-for="rec in getFoodRecipes(item)" :key="rec.id">
                                                <span class="text-sm text-blue-600 font-medium" x-text="rec.name"></span>
                                            </template>
                                        </div>
                                    </template>
                                    <template x-if="getFoodRecipes(item).length === 0">
                                        <span class="text-sm text-gray-400">No recipe</span>
                                    </template>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <span class="text-sm font-medium text-gray-900" x-show="item.price > 0"><span x-text="BmsMoney.symbol"></span> <span x-text="item.price.toLocaleString()"></span></span>
                                    <span class="text-xs text-gray-400" x-show="item.price === 0">-</span>
                                </td>
                                <td class="px-5 py-3 text-center">
                                    @if ($canFoodEdit)
                                    <button @click="toggleFoodAvailability(item)" :disabled="materialSaving === 'food-item'" :class="item.available ? 'bg-emerald-500' : 'bg-gray-300'" class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:opacity-60">
                                        <span :class="item.available ? 'translate-x-4' : 'translate-x-0.5'" class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition-transform duration-200"></span>
                                    </button>
                                    @else
                                    <span class="text-xs font-medium" :class="item.available ? 'text-emerald-700' : 'text-gray-500'" x-text="item.available ? 'Available' : 'Unavailable'"></span>
                                    @endif
                                </td>
                                @if ($canFoodEdit || $canFoodDelete)
                                <td class="px-5 py-3 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        @if ($canFoodEdit)
                                        <button @click="openEditFoodItem(item)" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Edit">@heroicon('o-pencil', 'w-4 h-4')</button>
                                        @endif
                                        @if ($canFoodDelete)
                                        <button @click="openDeleteFoodItem(item)" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">@heroicon('o-trash', 'w-4 h-4')</button>
                                        @endif
                                    </div>
                                </td>
                                @endif
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        @if ($canFoodAdd)
        {{-- Add Food Item Modal --}}
        <template x-teleport="body">
            <div x-show="showAddFoodItem" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.away="showAddFoodItem = false" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showAddFoodItem = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg z-10 overflow-hidden max-h-[85vh] overflow-y-auto" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                    <div class="sticky top-0 bg-white flex items-center justify-between px-6 py-4 border-b border-gray-200 z-10">
                        <h3 class="text-lg font-bold text-gray-900">Add Food Item</h3>
                        <button @click="showAddFoodItem = false" class="p-1 text-gray-400 hover:text-gray-600 rounded-lg">@heroicon('o-x-mark', 'w-5 h-5')</button>
                    </div>
                    <div class="px-6 py-5 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1.5">Item Name</label><input type="text" x-model="newFoodItem.name" placeholder="e.g. Chicken Burger" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Category</label><select x-model="newFoodItem.category" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none"><template x-for="cat in foodCategories" :key="cat.id"><option :value="cat.id" x-text="cat.label"></option></template></select></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Selling Price (<span x-text="BmsMoney.symbol"></span>)</label><input type="number" x-model="newFoodItem.price" placeholder="0" min="0" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"></div>
                            <div class="col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1.5">Expiry Date</label><input type="date" x-model="newFoodItem.expiryDate" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"></div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Recipes <span class="text-gray-400 font-normal">(select one or more)</span></label>
                            <div class="bg-gray-50 border border-gray-300 rounded-xl p-2 max-h-40 overflow-y-auto space-y-1">
                                <template x-for="r in recipes.filter(function(r){ return r.active; })" :key="r.id">
                                    <label class="flex items-center justify-between gap-2.5 px-2 py-1.5 rounded-lg hover:bg-white cursor-pointer transition-colors">
                                        <span class="flex items-center gap-2.5">
                                            <input type="checkbox" :value="r.id" x-model="newFoodItem.recipeIds" class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span class="text-sm text-gray-700" x-text="r.name + ' (' + r.code + ')'"></span>
                                        </span>
                                        <span class="text-xs font-medium text-gray-500" :class="newFoodItem.recipeIds.indexOf(r.id) > -1 ? 'text-blue-600' : 'text-gray-400'"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(recipeTotalCost(r))"></span></span>
                                    </label>
                                </template>
                            </div>
                            <template x-if="getFoodRecipes(newFoodItem).length > 0">
                                <div class="mt-2 space-y-1">
                                    <template x-for="r in getFoodRecipes(newFoodItem)" :key="r.id">
                                        <div class="flex items-center justify-between bg-blue-50 border border-blue-100 rounded-lg px-3 py-1.5 text-xs">
                                            <span class="font-medium text-blue-700" x-text="r.name"></span>
                                            <span class="font-semibold text-blue-700"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(recipeTotalCost(r))"></span></span>
                                        </div>
                                    </template>
                                    <p class="text-[11px] text-gray-400">Production cost of each selected recipe.</p>
                                </div>
                            </template>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Item Image</label>
                            <div class="flex items-center gap-3">
                                <template x-if="newFoodItem.image && isFoodImage(newFoodItem.image)"><img :src="newFoodItem.image" class="w-16 h-16 rounded-xl object-cover border border-gray-200"></template>
                                <template x-if="newFoodItem.image && !isFoodImage(newFoodItem.image)"><div class="w-16 h-16 rounded-xl bg-gray-100 border border-gray-200 flex items-center justify-center text-2xl" x-text="newFoodItem.image"></div></template>
                                <template x-if="!newFoodItem.image"><div class="w-16 h-16 rounded-xl bg-gray-100 border border-dashed border-gray-300 flex items-center justify-center"><span class="text-[10px] text-gray-400 text-center leading-tight px-1">No image</span></div></template>
                                <label class="cursor-pointer inline-flex items-center gap-1.5 h-10 px-4 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition-colors">
                                    @heroicon('o-arrow-up-tray', 'w-4 h-4')
                                    Upload Image
                                    <input type="file" accept="image/*" class="hidden" @change="handleFoodImageUpload($event, 'new')">
                                </label>
                                <button x-show="newFoodItem.image" @click="newFoodItem.image = ''" class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors" title="Remove image">@heroicon('o-x-mark', 'w-4 h-4')</button>
                            </div>
                            <p class="text-[11px] text-gray-400 mt-1.5">PNG, JPG or SVG. Max 2MB.</p>
                        </div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Instructions</label><textarea x-model="newFoodItem.instructions" rows="3" placeholder="Preparation instructions..." class="w-full px-4 py-2.5 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all resize-none"></textarea></div>
                        <div class="flex items-center gap-3">
                            <button @click="newFoodItem.available = !newFoodItem.available" :class="newFoodItem.available ? 'bg-emerald-500' : 'bg-gray-300'" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                                <span :class="newFoodItem.available ? 'translate-x-5' : 'translate-x-0.5'" class="inline-block h-5 w-5 transform rounded-full bg-white shadow-sm transition-transform duration-200"></span>
                            </button>
                            <span class="text-sm font-medium text-gray-700" x-text="newFoodItem.available ? 'Available' : 'Unavailable'"></span>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex gap-2">
                        <button @click="showAddFoodItem = false" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white transition-colors">Cancel</button>
                        <button @click="addFoodItem()" :disabled="materialSaving === 'food-item'" class="flex-1 inline-flex items-center justify-center gap-2 py-2.5 rounded-xl bg-blue-600 text-sm font-semibold text-white hover:bg-blue-700 transition-colors shadow-sm shadow-blue-200 disabled:opacity-60"><span x-show="materialSaving === 'food-item'" class="bms-progress-ring"></span><span x-text="materialSaving === 'food-item' ? 'Saving...' : 'Add Item'"></span></button>
                    </div>
                </div>
            </div>
        </template>
        @endif

        @if ($canFoodEdit)
        {{-- Edit Food Item Modal --}}
        <template x-teleport="body">
            <div x-show="showEditFoodItem" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.away="showEditFoodItem = false" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showEditFoodItem = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg z-10 overflow-hidden max-h-[85vh] overflow-y-auto" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                    <div class="sticky top-0 bg-white flex items-center justify-between px-6 py-4 border-b border-gray-200 z-10">
                        <h3 class="text-lg font-bold text-gray-900">Edit Food Item</h3>
                        <button @click="showEditFoodItem = false" class="p-1 text-gray-400 hover:text-gray-600 rounded-lg">@heroicon('o-x-mark', 'w-5 h-5')</button>
                    </div>
                    <div class="px-6 py-5 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1.5">Item Name</label><input type="text" x-model="editFoodItem.name" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Category</label><select x-model="editFoodItem.category" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none"><template x-for="cat in foodCategories" :key="cat.id"><option :value="cat.id" x-text="cat.label"></option></template></select></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Selling Price (<span x-text="BmsMoney.symbol"></span>)</label><input type="number" x-model="editFoodItem.price" min="0" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"></div>
                            <div class="col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1.5">Expiry Date</label><input type="date" x-model="editFoodItem.expiryDate" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"></div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Recipes <span class="text-gray-400 font-normal">(select one or more)</span></label>
                            <div class="bg-gray-50 border border-gray-300 rounded-xl p-2 max-h-40 overflow-y-auto space-y-1">
                                <template x-for="r in recipes.filter(function(r){ return r.active; })" :key="r.id">
                                    <label class="flex items-center justify-between gap-2.5 px-2 py-1.5 rounded-lg hover:bg-white cursor-pointer transition-colors">
                                        <span class="flex items-center gap-2.5">
                                            <input type="checkbox" :value="r.id" x-model="editFoodItem.recipeIds" class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span class="text-sm text-gray-700" x-text="r.name + ' (' + r.code + ')'"></span>
                                        </span>
                                        <span class="text-xs font-medium" :class="editFoodItem.recipeIds.indexOf(r.id) > -1 ? 'text-blue-600' : 'text-gray-400'"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(recipeTotalCost(r))"></span></span>
                                    </label>
                                </template>
                            </div>
                            <template x-if="getFoodRecipes(editFoodItem).length > 0">
                                <div class="mt-2 space-y-1">
                                    <template x-for="r in getFoodRecipes(editFoodItem)" :key="r.id">
                                        <div class="flex items-center justify-between bg-blue-50 border border-blue-100 rounded-lg px-3 py-1.5 text-xs">
                                            <span class="font-medium text-blue-700" x-text="r.name"></span>
                                            <span class="font-semibold text-blue-700"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(recipeTotalCost(r))"></span></span>
                                        </div>
                                    </template>
                                    <p class="text-[11px] text-gray-400">Production cost of each selected recipe.</p>
                                </div>
                            </template>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Item Image</label>
                            <div class="flex items-center gap-3">
                                <template x-if="editFoodItem.image && isFoodImage(editFoodItem.image)"><img :src="editFoodItem.image" class="w-16 h-16 rounded-xl object-cover border border-gray-200"></template>
                                <template x-if="editFoodItem.image && !isFoodImage(editFoodItem.image)"><div class="w-16 h-16 rounded-xl bg-gray-100 border border-gray-200 flex items-center justify-center text-2xl" x-text="editFoodItem.image"></div></template>
                                <template x-if="!editFoodItem.image"><div class="w-16 h-16 rounded-xl bg-gray-100 border border-dashed border-gray-300 flex items-center justify-center"><span class="text-[10px] text-gray-400 text-center leading-tight px-1">No image</span></div></template>
                                <label class="cursor-pointer inline-flex items-center gap-1.5 h-10 px-4 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition-colors">
                                    @heroicon('o-arrow-up-tray', 'w-4 h-4')
                                    Upload Image
                                    <input type="file" accept="image/*" class="hidden" @change="handleFoodImageUpload($event, 'edit')">
                                </label>
                                <button x-show="editFoodItem.image" @click="editFoodItem.image = ''" class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors" title="Remove image">@heroicon('o-x-mark', 'w-4 h-4')</button>
                            </div>
                            <p class="text-[11px] text-gray-400 mt-1.5">PNG, JPG or SVG. Max 2MB.</p>
                        </div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Instructions</label><textarea x-model="editFoodItem.instructions" rows="3" class="w-full px-4 py-2.5 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all resize-none"></textarea></div>
                        <div class="flex items-center gap-3">
                            <button @click="editFoodItem.available = !editFoodItem.available" :class="editFoodItem.available ? 'bg-emerald-500' : 'bg-gray-300'" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                                <span :class="editFoodItem.available ? 'translate-x-5' : 'translate-x-0.5'" class="inline-block h-5 w-5 transform rounded-full bg-white shadow-sm transition-transform duration-200"></span>
                            </button>
                            <span class="text-sm font-medium text-gray-700" x-text="editFoodItem.available ? 'Available' : 'Unavailable'"></span>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex gap-2">
                        <button @click="showEditFoodItem = false" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white transition-colors">Cancel</button>
                        <button @click="saveEditFoodItem()" :disabled="materialSaving === 'food-item'" class="flex-1 inline-flex items-center justify-center gap-2 py-2.5 rounded-xl bg-blue-600 text-sm font-semibold text-white hover:bg-blue-700 transition-colors shadow-sm shadow-blue-200 disabled:opacity-60"><span x-show="materialSaving === 'food-item'" class="bms-progress-ring"></span><span x-text="materialSaving === 'food-item' ? 'Saving...' : 'Save Changes'"></span></button>
                    </div>
                </div>
            </div>
        </template>
        @endif
    </div>

    {{-- RAW MATERIALS TAB --}}
    <div x-show="activeTab === 'raw-materials'" x-cloak>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
            <div>
                <h2 class="text-lg font-bold text-gray-900">Raw Materials</h2>
                <p class="mt-1 text-sm text-gray-500">Manage ingredients and raw materials used in recipes.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 mt-4 sm:mt-0">
                @if ($canRawAdd)
                <a href="{{ route('kitchen.materials.template') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-gray-200 text-gray-700 text-sm font-semibold rounded-xl hover:bg-gray-50 transition-colors">
                    @heroicon('o-arrow-down-tray', 'w-4 h-4')
                    Template
                </a>
                <label class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-gray-200 text-gray-700 text-sm font-semibold rounded-xl hover:bg-gray-50 transition-colors cursor-pointer">
                    <span x-show="importMaterialsSaving" class="bms-progress-ring"></span>
                    <span x-show="!importMaterialsSaving">@heroicon('o-arrow-up-tray', 'w-4 h-4')</span>
                    Import
                    <input type="file" accept=".csv,.txt,.xlsx,.xls" class="hidden" :disabled="importMaterialsSaving" @change="importRawMaterials($event)">
                </label>
                <button @click="openRestockModal()" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-600 text-white text-sm font-semibold rounded-xl hover:bg-emerald-700 transition-colors shadow-sm shadow-emerald-200">
                    @heroicon('o-arrow-path', 'w-4 h-4')
                    Restock
                </button>
                @endif
                @if ($canRawEdit)
                <button @click="openAdjustModal()" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-amber-500 text-white text-sm font-semibold rounded-xl hover:bg-amber-600 transition-colors shadow-sm shadow-amber-200">
                    @heroicon('o-adjustments-horizontal', 'w-4 h-4')
                    Adjust
                </button>
                @endif
                @if ($canRawAdd)
                <button @click="openAddRawMaterial()" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-blue-600 text-white text-sm font-semibold rounded-xl hover:bg-blue-700 transition-colors shadow-sm shadow-blue-200">
                    @heroicon('o-plus', 'w-4 h-4')
                    Add Ingredient
                </button>
                @endif
            </div>
        </div>

        {{-- Summary Card --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-5 overflow-hidden">
            <div class="grid grid-cols-1 {{ $canRawValue ? 'sm:grid-cols-3' : 'sm:grid-cols-2' }} divide-y sm:divide-y-0 sm:divide-x divide-gray-100">
                <div class="p-5 flex items-center gap-4">
                    <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
                        @heroicon('o-cube', 'w-5 h-5 text-blue-600')
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-gray-500">Number of Raw Materials</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="rawMaterialCount"></p>
                        <p class="text-[11px] text-gray-400"><span x-text="rawMaterialCategoryCount"></span> categories</p>
                    </div>
                </div>
                @if ($canRawValue)
                <div class="p-5 flex items-center gap-4">
                    <div class="w-11 h-11 rounded-xl bg-emerald-50 flex items-center justify-center flex-shrink-0">
                        @heroicon('o-banknotes', 'w-5 h-5 text-emerald-600')
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-gray-500">Stock Value</p>
                        <p class="text-2xl font-bold text-gray-900 truncate"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(rawMaterialStockValue)"></span></p>
                        <p class="text-[11px] text-gray-400">at average cost</p>
                    </div>
                </div>
                @endif
                <div class="p-5 flex items-center gap-4">
                    <div class="w-11 h-11 rounded-xl bg-amber-50 flex items-center justify-center flex-shrink-0">
                        @heroicon('o-exclamation-triangle', 'w-5 h-5 text-amber-600')
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-gray-500">Below Alert Level</p>
                        <p class="text-2xl font-bold" :class="rawMaterialLowStockCount > 0 ? 'text-amber-600' : 'text-gray-900'" x-text="rawMaterialLowStockCount"></p>
                        <p class="text-[11px] text-gray-400">need restocking</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
            <div class="relative flex-1 max-w-xs">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" x-model.debounce.300ms="rmSearchQuery" placeholder="Search ingredients..." class="w-full h-9 pl-9 pr-4 text-sm bg-white border border-gray-200 rounded-lg focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
            </div>
            <div class="flex items-center gap-2 overflow-x-auto no-scrollbar">
                <button @click="rmCategoryFilter = 'all'" :class="rmCategoryFilter === 'all' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all whitespace-nowrap">All</button>
                <template x-for="cat in rawMaterialCategories" :key="cat">
                    <button @click="rmCategoryFilter = cat" :class="rmCategoryFilter === cat ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all whitespace-nowrap" x-text="cat"></button>
                </template>
            </div>
            <div class="flex items-center gap-2">
                <button @click="rmStatusFilter = 'all'" :class="rmStatusFilter === 'all' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all">All Status</button>
                <button @click="rmStatusFilter = 'low'" :class="rmStatusFilter === 'low' ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all">Low Stock</button>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/50">
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Ingredient</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">SKU</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Category</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Stock</th>
                            <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Alert Level</th>
                            @if ($canRawValue)
                            <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Cost/Unit</th>
                            @endif
                            <th class="text-center px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Expiry</th>
                            @if ($canRawEdit || $canRawDelete)
                            <th class="text-center px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template x-for="m in filteredRawMaterials" :key="m.id">
                            <tr class="hover:bg-gray-50/40 transition-colors cursor-pointer" @click="openRawMaterialDetail(m)">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2.5">
                                        <span class="text-lg" x-text="m.image"></span>
                                        <div>
                                            <p class="font-medium text-gray-900" x-text="m.name"></p>
                                            <p class="text-xs text-gray-400" x-text="m.supplier"></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-gray-500 font-mono text-xs" x-text="m.sku"></td>
                                <td class="px-5 py-3"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-600" x-text="m.category"></span></td>
                                <td class="px-5 py-3 text-right font-medium" x-text="m.currentQty + ' ' + m.unit"></td>
                                <td class="px-5 py-3 text-right text-gray-500" x-text="m.alertLevel + ' ' + m.unit"></td>
                                @if ($canRawValue)
                                <td class="px-5 py-3 text-right text-gray-900"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(m.costPerUnit)"></span></td>
                                @endif
                                <td class="px-5 py-3 text-center"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium" :class="rmStockStatus(m).color" x-text="rmStockStatus(m).label"></span></td>
                                <td class="px-5 py-3"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium" :class="rmExpiryStatus(m).color" x-text="m.expiryDate"></span></td>
                                @if ($canRawEdit || $canRawDelete)
                                <td class="px-5 py-3 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        @if ($canRawEdit)
                                        <button @click.stop="openEditRawMaterial(m)" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Edit">@heroicon('o-pencil', 'w-4 h-4')</button>
                                        @endif
                                        @if ($canRawDelete)
                                        <button @click.stop="openDeleteRawMaterial(m)" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">@heroicon('o-trash', 'w-4 h-4')</button>
                                        @endif
                                    </div>
                                </td>
                                @endif
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Raw Material Detail Drawer --}}
        <template x-teleport="body">
            <div x-show="showRawMaterialDetail" x-cloak class="fixed inset-0 z-50 flex justify-end" @keydown.escape.window="showRawMaterialDetail = false">
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showRawMaterialDetail = false"></div>
                <div class="relative w-full max-w-lg bg-white shadow-2xl z-10 overflow-y-auto" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full">
                    <template x-if="selectedRawMaterial">
                        <div>
                            <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between z-10">
                                <div class="flex items-center gap-3">
                                    <span class="text-2xl" x-text="selectedRawMaterial.image"></span>
                                    <div>
                                        <h3 class="text-lg font-bold text-gray-900" x-text="selectedRawMaterial.name"></h3>
                                        <p class="text-xs text-gray-500" x-text="selectedRawMaterial.sku"></p>
                                    </div>
                                </div>
                                <button @click="showRawMaterialDetail = false" class="p-1 text-gray-400 hover:text-gray-600 rounded-lg">@heroicon('o-x-mark', 'w-5 h-5')</button>
                            </div>
                            <div class="px-6 py-5 space-y-6">
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="bg-gray-50 rounded-xl p-3"><p class="text-xs text-gray-500">Current Stock</p><p class="text-lg font-bold text-gray-900" x-text="selectedRawMaterial.currentQty + ' ' + selectedRawMaterial.unit"></p></div>
                                    <div class="bg-gray-50 rounded-xl p-3"><p class="text-xs text-gray-500">Status</p><span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium mt-1" :class="rmStockStatus(selectedRawMaterial).color" x-text="rmStockStatus(selectedRawMaterial).label"></span></div>
                                    @if ($canRawValue)
                                    <div class="bg-gray-50 rounded-xl p-3"><p class="text-xs text-gray-500">Average Cost</p><p class="text-sm font-bold text-gray-900"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(selectedRawMaterial.avgCost)"></span>/<span x-text="selectedRawMaterial.unit"></span></p></div>
                                    @endif
                                    <div class="bg-gray-50 rounded-xl p-3"><p class="text-xs text-gray-500">Expiry Date</p><p class="text-sm font-bold" :class="rmExpiryStatus(selectedRawMaterial).color.includes('red') ? 'text-red-600' : rmExpiryStatus(selectedRawMaterial).color.includes('amber') ? 'text-amber-600' : 'text-green-600'" x-text="selectedRawMaterial.expiryDate"></p></div>
                                </div>
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 mb-3">Details</h4>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between"><span class="text-gray-500">Category</span><span class="font-medium" x-text="selectedRawMaterial.category"></span></div>
                                        <div class="flex justify-between"><span class="text-gray-500">Unit</span><span class="font-medium" x-text="selectedRawMaterial.unit"></span></div>
                                        <div class="flex justify-between"><span class="text-gray-500">Alert Level</span><span class="font-medium" x-text="selectedRawMaterial.alertLevel + ' ' + selectedRawMaterial.unit"></span></div>
                                        <div class="flex justify-between"><span class="text-gray-500">Supplier</span><span class="font-medium" x-text="selectedRawMaterial.supplier"></span></div>
                                        <div class="flex justify-between"><span class="text-gray-500">Batch No.</span><span class="font-medium font-mono text-xs" x-text="selectedRawMaterial.batchNo"></span></div>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 mb-3">Stock Level</h4>
                                    <div class="w-full bg-gray-100 rounded-full h-3">
                                        <div class="h-3 rounded-full transition-all duration-500" :class="selectedRawMaterial.currentQty <= selectedRawMaterial.alertLevel ? 'bg-red-500' : 'bg-green-500'" :style="'width:' + Math.min((selectedRawMaterial.currentQty / ((selectedRawMaterial.alertLevel || 1) * 2)) * 100, 100) + '%'"></div>
                                    </div>
                                    <div class="flex justify-between mt-1 text-[10px] text-gray-400"><span>0</span><span x-text="'Alert: ' + selectedRawMaterial.alertLevel"></span><span x-text="'High: ' + ((selectedRawMaterial.alertLevel || 1) * 2) + ' ' + selectedRawMaterial.unit"></span></div>
                                </div>
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 mb-3">Stock Movement Ledger</h4>
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-3 text-sm bg-green-50/50 rounded-lg p-2.5">
                                            <span class="text-green-500">+</span>
                                            <div class="flex-1"><span class="text-gray-700">Purchase</span><span class="text-xs text-gray-400 ml-2">PO-1023</span></div>
                                            <span class="font-medium text-green-600">+20 kg</span>
                                            <span class="text-xs text-gray-400">52.5 kg</span>
                                        </div>
                                        <div class="flex items-center gap-3 text-sm bg-red-50/50 rounded-lg p-2.5">
                                            <span class="text-red-500">-</span>
                                            <div class="flex-1"><span class="text-gray-700">Consumption</span><span class="text-xs text-gray-400 ml-2">POS-001</span></div>
                                            <span class="font-medium text-red-600">-15 kg</span>
                                            <span class="text-xs text-gray-400">37.5 kg</span>
                                        </div>
                                        <div class="flex items-center gap-3 text-sm bg-blue-50/50 rounded-lg p-2.5">
                                            <span class="text-blue-500">~</span>
                                            <div class="flex-1"><span class="text-gray-700">Adjustment</span><span class="text-xs text-gray-400 ml-2">ADJ-003</span></div>
                                            <span class="font-medium text-blue-600">-3 kg</span>
                                            <span class="text-xs text-gray-400">32.5 kg</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        @if ($canRawAdd)
        {{-- Add Raw Material Modal --}}
        <template x-teleport="body">
            <div x-show="showAddRawMaterial" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.away="showAddRawMaterial = false" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showAddRawMaterial = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg z-10 overflow-hidden max-h-[85vh] overflow-y-auto" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                    <div class="sticky top-0 bg-white flex items-center justify-between px-6 py-4 border-b border-gray-200 z-10">
                        <h3 class="text-lg font-bold text-gray-900">Add Raw Material</h3>
                        <button @click="showAddRawMaterial = false" class="p-1 text-gray-400 hover:text-gray-600 rounded-lg">@heroicon('o-x-mark', 'w-5 h-5')</button>
                    </div>
                    <div class="px-6 py-5 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1.5">Name</label><input type="text" x-model="newRawMaterial.name" placeholder="e.g. Chicken Breast" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">SKU</label><input type="text" x-model="newRawMaterial.sku" placeholder="Auto-generated" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Category</label><select x-model="newRawMaterial.category" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none"><template x-for="cat in rawMaterialCategories" :key="cat"><option :value="cat" x-text="cat"></option></template></select></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Unit</label><select x-model="newRawMaterial.unit" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none"><template x-for="u in rawMaterialUnits" :key="u"><option :value="u" x-text="u"></option></template></select></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Current Quantity</label><input type="number" x-model="newRawMaterial.currentQty" placeholder="0" min="0" step="0.1" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"></div>
<div><label class="block text-sm font-medium text-gray-700 mb-1.5">Alert Level</label><input type="number" x-model="newRawMaterial.alertLevel" placeholder="0" min="0" step="0.1" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all" title="Lowest stock level allowed before the system flags an alert"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Cost Per Unit (<span x-text="BmsMoney.symbol"></span>)</label><input type="number" x-model="newRawMaterial.costPerUnit" placeholder="0" min="0" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"></div>
<div><label class="block text-sm font-medium text-gray-700 mb-1.5">Supplier</label><input type="text" x-model="newRawMaterial.supplier" placeholder="Supplier name" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Expiry Date</label><input type="date" x-model="newRawMaterial.expiryDate" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"></div>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex gap-2">
                        <button @click="showAddRawMaterial = false" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white transition-colors">Cancel</button>
                        <button @click="addRawMaterial()" :disabled="materialSaving === 'create'" class="flex-1 inline-flex items-center justify-center gap-2 py-2.5 rounded-xl bg-blue-600 text-sm font-semibold text-white hover:bg-blue-700 transition-colors shadow-sm shadow-blue-200 disabled:opacity-60"><span x-show="materialSaving === 'create'" class="bms-progress-ring"></span><span x-text="materialSaving === 'create' ? 'Saving...' : 'Add Material'"></span></button>
                    </div>
                </div>
            </div>
        </template>
        @endif

        @if ($canRawEdit)
        {{-- Edit Raw Material Modal --}}
        <template x-teleport="body">
            <div x-show="showEditRawMaterial" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.away="showEditRawMaterial = false" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showEditRawMaterial = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg z-10 overflow-hidden max-h-[85vh] overflow-y-auto" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                    <div class="sticky top-0 bg-white flex items-center justify-between px-6 py-4 border-b border-gray-200 z-10">
                        <h3 class="text-lg font-bold text-gray-900">Edit Raw Material</h3>
                        <button @click="showEditRawMaterial = false" class="p-1 text-gray-400 hover:text-gray-600 rounded-lg">@heroicon('o-x-mark', 'w-5 h-5')</button>
                    </div>
                    <div class="px-6 py-5 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1.5">Name</label><input type="text" x-model="editRawMaterial.name" placeholder="e.g. Chicken Breast" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">SKU</label><input type="text" x-model="editRawMaterial.sku" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Category</label><select x-model="editRawMaterial.category" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none"><template x-for="cat in rawMaterialCategories" :key="cat"><option :value="cat" x-text="cat"></option></template></select></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Unit</label><select x-model="editRawMaterial.unit" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none"><template x-for="u in rawMaterialUnits" :key="u"><option :value="u" x-text="u"></option></template></select></div>
                            <div class="col-span-2 rounded-xl border border-blue-100 bg-blue-50 px-3 py-2.5 text-xs text-blue-800">Current quantity: <strong x-text="editingRawMaterial?.currentQty"></strong> <span x-text="editingRawMaterial?.unit"></span>. Change stock using Restock or Adjust so the movement is recorded.</div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Alert Level</label><input type="number" x-model="editRawMaterial.alertLevel" placeholder="0" min="0" step="0.1" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all" title="Lowest stock level allowed before the system flags an alert"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Cost Per Unit (<span x-text="BmsMoney.symbol"></span>)</label><input type="number" x-model="editRawMaterial.costPerUnit" placeholder="0" min="0" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Supplier</label><input type="text" x-model="editRawMaterial.supplier" placeholder="Supplier name" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"></div>
                            <div class="col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1.5">Expiry Date</label><input type="date" x-model="editRawMaterial.expiryDate" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"></div>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex gap-2">
                        <button @click="showEditRawMaterial = false" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white transition-colors">Cancel</button>
                        <button @click="saveEditRawMaterial()" :disabled="materialSaving === 'edit'" class="flex-1 inline-flex items-center justify-center gap-2 py-2.5 rounded-xl bg-blue-600 text-sm font-semibold text-white hover:bg-blue-700 transition-colors shadow-sm shadow-blue-200 disabled:opacity-60"><span x-show="materialSaving === 'edit'" class="bms-progress-ring"></span><span x-text="materialSaving === 'edit' ? 'Saving...' : 'Save Changes'"></span></button>
                    </div>
                </div>
            </div>
        </template>
        @endif

        @if ($canRawDelete)
        {{-- Delete Raw Material Confirm Modal --}}
        <template x-teleport="body">
            <div x-show="showDeleteRawMaterial" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.away="showDeleteRawMaterial = false" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showDeleteRawMaterial = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md z-10 overflow-hidden" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                    <div class="p-6 text-center">
                        <div class="mx-auto w-14 h-14 rounded-full bg-red-50 flex items-center justify-center mb-4">
                            @heroicon('o-exclamation-triangle', 'w-7 h-7 text-red-600')
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 mb-1">Delete Raw Material?</h3>
                        <template x-if="rawMaterialToDelete">
                            <p class="text-sm text-gray-500">You are about to permanently delete <span class="font-semibold text-gray-900" x-text="rawMaterialToDelete.name"></span>. This action cannot be undone.</p>
                        </template>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex gap-2">
                        <button @click="showDeleteRawMaterial = false" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white transition-colors">Cancel</button>
                        <button @click="confirmDeleteRawMaterial()" :disabled="materialSaving === 'delete'" class="flex-1 inline-flex items-center justify-center gap-2 py-2.5 rounded-xl bg-red-600 text-sm font-semibold text-white hover:bg-red-700 transition-colors shadow-sm shadow-red-200 disabled:opacity-60"><span x-show="materialSaving === 'delete'" class="bms-progress-ring"></span><span x-text="materialSaving === 'delete' ? 'Deleting...' : 'Delete'"></span></button>
                    </div>
                </div>
            </div>
        </template>
        @endif
        @if ($canRawAdd)
        {{-- Raw Material Import Preview --}}
        <template x-teleport="body">
            <div x-show="showImportMaterials" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-5" @keydown.escape.window="if (!importMaterialsSaving) showImportMaterials = false">
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="if (!importMaterialsSaving) showImportMaterials = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-5xl z-10 overflow-hidden max-h-[92vh] flex flex-col">
                    <div class="px-5 py-4 sm:px-6 border-b border-gray-200 flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">Preview Raw Material Import</h3>
                            <p class="mt-1 text-sm text-gray-500">Review rows, choose a shared category and unit, then confirm the import.</p>
                        </div>
                        <button @click="if (!importMaterialsSaving) showImportMaterials = false" class="p-1 text-gray-400 hover:text-gray-600 rounded-lg" aria-label="Close preview">@heroicon('o-x-mark', 'w-5 h-5')</button>
                    </div>
                    <div class="p-5 sm:p-6 overflow-y-auto space-y-5">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="block text-sm font-medium text-gray-700">Category for all imported rows
                                <select x-model="importMaterialCategoryId" class="mt-1.5 w-full h-10 px-3 bg-white border border-gray-300 rounded-xl focus:border-blue-500 outline-none">
                                    <option value="">Select category</option>
                                    <template x-for="category in importMaterialCategories" :key="category.id">
                                        <option :value="category.id" x-text="category.name"></option>
                                    </template>
                                </select>
                            </label>
                            <label class="block text-sm font-medium text-gray-700">Unit for all imported rows
                                <select x-model="importMaterialUnit" class="mt-1.5 w-full h-10 px-3 bg-white border border-gray-300 rounded-xl focus:border-blue-500 outline-none">
                                    <template x-for="unit in rawMaterialUnits" :key="unit">
                                        <option :value="unit" x-text="unit"></option>
                                    </template>
                                </select>
                            </label>
                        </div>
                        <div x-show="!importMaterialCategories.length" class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">Create a raw-material category in Settings before importing.</div>
                        <div class="flex flex-wrap gap-2 text-xs">
                            <span class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-700" x-text="importMaterialSummary.total + ' rows found'"></span>
                            <span class="px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700" x-text="importMaterialSummary.valid + ' valid'"></span>
                            <span class="px-2.5 py-1 rounded-full bg-red-50 text-red-700" x-text="importMaterialSummary.invalid + ' need attention'"></span>
                        </div>
                        <div class="overflow-x-auto border border-gray-200 rounded-xl">
                            <table class="min-w-[900px] w-full text-left text-sm">
                                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="px-3 py-3">Import</th><th class="px-3 py-3">Spreadsheet row</th><th class="px-3 py-3">Name</th><th class="px-3 py-3">Current Qty</th><th class="px-3 py-3">Alert Level</th><th class="px-3 py-3">Cost / Unit</th><th class="px-3 py-3">Supplier</th><th class="px-3 py-3">Expiry</th><th class="px-3 py-3">Validation</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="row in importMaterialRows" :key="row.row">
                                        <tr :class="row.valid ? '' : 'bg-red-50/60'">
                                            <td class="px-3 py-3"><input type="checkbox" :value="row.row" x-model.number="importMaterialSelectedRows" :disabled="!row.valid" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"></td>
                                            <td class="px-3 py-3 text-gray-500" x-text="row.row"></td>
                                            <td class="px-3 py-3 font-medium text-gray-900">
                                                <span x-text="row.name"></span>
                                                <span x-show="row.is_sample" class="ml-1 inline-flex px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 text-[10px] font-semibold">DEMO ROW</span>
                                            </td>
                                            <td class="px-3 py-3" x-text="row.current_quantity"></td><td class="px-3 py-3" x-text="row.alert_level || '—'"></td><td class="px-3 py-3" x-text="row.cost_per_unit"></td><td class="px-3 py-3" x-text="row.supplier || '—'"></td><td class="px-3 py-3" x-text="row.expiry_date || '—'"></td>
                                            <td class="px-3 py-3 text-xs" :class="row.valid ? 'text-emerald-700' : 'text-red-700'" x-text="row.valid ? 'Valid' : row.errors.join(' ')"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <p class="text-xs text-gray-500">The template's Chicken Breast example is marked as a demo row and is excluded by default. Uncheck any other rows you do not want to import.</p>
                        <div x-show="importMaterialsError" class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700" x-text="importMaterialsError"></div>
                    </div>
                    <div class="px-5 py-4 sm:px-6 bg-gray-50 border-t border-gray-200 flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
                        <button @click="showImportMaterials = false" :disabled="importMaterialsSaving" class="px-4 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white disabled:opacity-60">Cancel</button>
                        <button @click="confirmImportRawMaterials()" :disabled="importMaterialsSaving || !importMaterialCategoryId || !importMaterialSelectedRows.length" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                            <span x-show="importMaterialsSaving" class="bms-progress-ring"></span>
                            <span x-text="importMaterialsSaving ? 'Importing...' : 'Import selected rows'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </template>
        @endif
        @if ($canFoodDelete)
        {{-- Delete Food Item Confirm Modal --}}
        <template x-teleport="body">
            <div x-show="showDeleteFoodItem" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.away="closeDeleteFoodItem()" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="closeDeleteFoodItem()"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md z-10 overflow-hidden" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                    <div class="p-6 text-center">
                        <div class="mx-auto w-14 h-14 rounded-full bg-red-50 flex items-center justify-center mb-4">
                            @heroicon('o-exclamation-triangle', 'w-7 h-7 text-red-600')
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 mb-1">Delete Food Item?</h3>
                        <template x-if="foodItemToDelete">
                            <p class="text-sm text-gray-500">You are about to permanently delete <span class="font-semibold text-gray-900" x-text="foodItemToDelete.name"></span>. This action cannot be undone.</p>
                        </template>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex gap-2">
                        <button @click="closeDeleteFoodItem()" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white transition-colors">Cancel</button>
                        <button @click="confirmDeleteFoodItem()" :disabled="materialSaving === 'food-delete'" class="flex-1 inline-flex items-center justify-center gap-2 py-2.5 rounded-xl bg-red-600 text-sm font-semibold text-white hover:bg-red-700 transition-colors shadow-sm shadow-red-200 disabled:opacity-60"><span x-show="materialSaving === 'food-delete'" class="bms-progress-ring"></span><span x-text="materialSaving === 'food-delete' ? 'Deleting...' : 'Delete'"></span></button>
                    </div>
                </div>
            </div>
        </template>
        @endif

        @if ($canRawAdd)
        {{-- Restock Raw Materials Modal (large) --}}
        <template x-teleport="body">
            <div x-show="showRestockModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden p-3 sm:p-6" @keydown.escape.window="closeRestockModal()" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                <div class="fixed inset-0 bg-black/30" @click="closeRestockModal()"></div>
                <div class="relative z-10 flex flex-col w-full max-w-2xl sm:max-w-4xl h-[520px] sm:h-[640px] max-h-[calc(100dvh-1.5rem)] sm:max-h-[calc(100dvh-3rem)] overflow-hidden bg-white rounded-2xl shadow-2xl" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                    {{-- Header --}}
                    <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-200 flex-shrink-0">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center flex-shrink-0">
                                @heroicon('o-arrow-path', 'w-5 h-5 text-emerald-600')
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Restock Raw Materials</h3>
                                <p class="text-xs text-gray-500">Record received quantity, unit cost and expiry for each item. Leave a row blank to skip it.</p>
                            </div>
                        </div>
                        <button @click="closeRestockModal()" class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">@heroicon('o-x-mark', 'w-5 h-5')</button>
                    </div>

                    {{-- Toolbar --}}
                    <div class="px-5 py-3 bg-gray-50/70 border-b border-gray-100 flex-shrink-0">
                        <div class="grid grid-cols-2 gap-2 sm:gap-3">
                            <div class="relative col-span-2 sm:col-span-1">
                                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                <input type="text" x-model.debounce.300ms="restockSearch" placeholder="Search by name or SKU..." class="w-full h-9 pl-9 pr-3 text-sm bg-white border border-gray-200 rounded-lg focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none transition-all">
                            </div>
                            <select x-model="restockCategory" class="col-span-2 sm:col-span-1 h-9 px-3 text-sm bg-white border border-gray-200 rounded-lg focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none">
                                <option value="all">All Categories</option>
                                <template x-for="cat in rawMaterialCategories" :key="cat"><option :value="cat" x-text="cat"></option></template>
                            </select>
                        </div>
                    </div>

                    {{-- Table --}}
                    <div class="flex-1 min-h-0 overflow-y-auto overflow-x-auto overscroll-contain">
                        <table class="w-full min-w-[720px] text-sm">
                            <thead class="sticky top-0 z-10">
                                <tr class="border-b border-gray-100 bg-white">
                                    <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Material</th>
                                    <th class="text-right px-2 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Current</th>
                                    <th class="text-left px-2 py-2.5 text-xs font-semibold text-emerald-600 uppercase tracking-wider w-24 sm:w-28">Add Qty</th>
                                    <th class="text-left px-2 py-2.5 text-xs font-semibold text-emerald-600 uppercase tracking-wider w-28 sm:w-32">Cost / Unit</th>
                                    <th class="text-left px-2 py-2.5 text-xs font-semibold text-emerald-600 uppercase tracking-wider w-32 sm:w-40">Expiry Date</th>
                                    <th class="text-right px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">New Stock</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <template x-for="m in restockList" :key="m.id">
                                    <tr class="transition-colors" :class="restockQty(m.id) > 0 ? 'bg-emerald-50/40' : 'hover:bg-gray-50/50'">
                                        <td class="px-5 py-2.5">
                                            <div class="flex items-center gap-2.5">
                                                <span class="text-lg flex-shrink-0" x-text="m.image"></span>
                                                <div class="min-w-0">
                                                    <p class="font-medium text-gray-900 truncate" x-text="m.name"></p>
                                                    <p class="text-[11px] text-gray-400 truncate"><span class="font-mono" x-text="m.sku"></span> &middot; <span x-text="m.category"></span> &middot; <span x-text="m.supplier"></span></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-2 py-2.5 text-right whitespace-nowrap"><span class="font-medium text-gray-700" x-text="parseFloat(m.currentQty).toFixed(2).replace(/\.00$/, '')"></span> <span class="text-[11px] text-gray-400" x-text="m.unit"></span></td>
                                        <td class="px-2 py-2.5">
                                            <div class="relative">
                                                <input type="number" min="0" step="0.01" x-model="restockLines[m.id]" placeholder="0" class="w-full h-9 pl-2 pr-7 text-sm text-right bg-white border rounded-lg outline-none transition-all focus:ring-2" :class="restockQty(m.id) > 0 ? 'border-emerald-400 focus:border-emerald-500 focus:ring-emerald-500/20' : 'border-gray-200 focus:border-emerald-500 focus:ring-emerald-500/20'">
                                                <span class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] text-gray-400 pointer-events-none" x-text="m.unit"></span>
                                            </div>
                                        </td>
                                        <td class="px-2 py-2.5">
                                            <div class="relative">
                                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-[10px] text-gray-400 pointer-events-none"><span x-text="BmsMoney.symbol"></span></span>
                                                <input type="number" min="0" step="1" x-model="restockCosts[m.id]" class="w-full h-9 pl-7 pr-2 text-sm text-right bg-white border rounded-lg outline-none transition-all focus:ring-2" :class="restockQty(m.id) > 0 ? (restockCostChanged(m) ? 'border-blue-400 text-blue-700 focus:border-blue-500 focus:ring-blue-500/20' : 'border-emerald-400 focus:border-emerald-500 focus:ring-emerald-500/20') : 'border-gray-200 text-gray-500 focus:border-emerald-500 focus:ring-emerald-500/20'">
                                            </div>
                                            <p class="mt-1 text-[10px] truncate" :class="restockQty(m.id) > 0 ? (restockCostChanged(m) ? 'text-blue-600' : 'text-gray-400') : 'text-gray-300'">
                                                <span x-show="restockQty(m.id) > 0 && restockCostChanged(m)">was <span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(restockCurrentCost(m))"></span></span>
                                                <span x-show="restockQty(m.id) > 0 && !restockCostChanged(m)">avg <span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(restockCurrentCost(m))"></span></span>
                                            </p>
                                        </td>
                                        <td class="px-2 py-2.5">
                                            <input type="date" x-model="restockExpiry[m.id]" class="w-full h-9 px-2 text-xs bg-white border rounded-lg outline-none transition-all focus:ring-2" :class="restockQty(m.id) > 0 ? restockExpiryState(m).input : 'border-gray-200 text-gray-500 focus:border-emerald-500 focus:ring-emerald-500/20'">
                                            <p class="mt-1 text-[10px] font-medium" :class="restockQty(m.id) > 0 ? restockExpiryState(m).hint : 'text-gray-300'">
                                                <span x-show="restockQty(m.id) > 0 && restockExpiryState(m).label" x-text="restockExpiryState(m).label"></span>
                                            </p>
                                        </td>
                                        <td class="px-5 py-2.5 text-right whitespace-nowrap">
                                            <span class="font-bold" :class="restockQty(m.id) > 0 ? 'text-emerald-600' : 'text-gray-400'" x-text="restockNewQty(m)"></span>
                                            <span class="text-[11px] text-gray-400" x-text="m.unit"></span>
                                            <span class="block text-[10px] text-emerald-600" x-show="restockQty(m.id) > 0" x-text="'+ ' + BmsMoney.symbol + ' ' + formatMoney(restockLineValue(m))"></span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        <div x-show="restockList.length === 0" class="py-12 text-center text-gray-400 text-sm">No raw materials match your search.</div>
                    </div>

                    {{-- Footer --}}
                    <div class="px-5 py-3.5 bg-gray-50 border-t border-gray-200 flex-shrink-0">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-x-5 gap-y-1 text-xs">
                                <span class="text-gray-500">Items: <span class="font-bold text-gray-900" x-text="restockLineCount"></span></span>
                                <span class="text-gray-500">Total units: <span class="font-bold text-gray-900" x-text="parseFloat(restockTotalUnits.toFixed(2))"></span></span>
                                <span class="text-gray-500">Value added: <span class="font-bold text-emerald-600"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(restockTotalValue)"></span></span></span>
                                <span class="inline-flex items-center gap-1 text-amber-600 font-medium" x-show="restockHasIssues" x-cloak>
                                    @heroicon('o-exclamation-triangle', 'w-3.5 h-3.5')
                                    Check expiry dates
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button @click="closeRestockModal()" class="flex-1 sm:flex-none px-4 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white transition-colors">Cancel</button>
                                <button @click="saveRestock()" :disabled="materialSaving === 'restock'" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-emerald-600 text-sm font-semibold text-white hover:bg-emerald-700 transition-colors shadow-sm shadow-emerald-200 disabled:opacity-60">
                                    <span x-show="materialSaving === 'restock'" class="bms-progress-ring"></span>
                                    <span x-show="materialSaving !== 'restock'">@heroicon('o-check', 'w-4 h-4')</span>
                                    <span x-text="materialSaving === 'restock' ? 'Restocking...' : 'Save Restock'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
        @endif

        @if ($canRawEdit)
        {{-- Adjust Stock Modal (large) --}}
        <template x-teleport="body">
            <div x-show="showAdjustModal" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto overscroll-contain p-3 sm:p-6" @keydown.escape.window="closeAdjustModal()" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                <div class="fixed inset-0 bg-black/30" @click="closeAdjustModal()"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl my-auto z-10 flex flex-col max-h-[70vh] overflow-hidden" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                    {{-- Header --}}
                    <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-200 flex-shrink-0">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center flex-shrink-0">
                                @heroicon('o-adjustments-horizontal', 'w-5 h-5 text-amber-600')
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Adjust Stock</h3>
                                <p class="text-xs text-gray-500">Enter a quantity and pick a reason per material. Leave a row blank to skip it.</p>
                            </div>
                        </div>
                        <button @click="closeAdjustModal()" class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">@heroicon('o-x-mark', 'w-5 h-5')</button>
                    </div>

                    {{-- Legend: what each reason does --}}
                    <div class="px-5 py-3 bg-gray-50/70 border-b border-gray-100 flex-shrink-0">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <template x-for="r in adjustReasonOptions" :key="r.id">
                                <div class="flex items-start gap-2 px-2.5 py-2 rounded-lg bg-white border" :class="rmToneClass(r.tone)">
                                    @heroicon('o-scale', 'w-4 h-4 mt-0.5 flex-shrink-0')
                                    <div class="min-w-0">
                                        <p class="text-[11px] font-bold leading-tight" x-text="r.label"></p>
                                        <p class="text-[10px] font-normal opacity-80 leading-tight mt-0.5" x-text="r.hint"></p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Search + Category --}}
                    <div class="px-5 py-3.5 bg-gray-50/70 border-b border-gray-100 flex-shrink-0">
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 sm:gap-3">
                            <div class="relative col-span-2 sm:col-span-2">
                                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                <input type="text" x-model.debounce.300ms="adjustSearch" placeholder="Search by name or SKU..." class="w-full h-9 pl-9 pr-3 text-sm bg-white border border-gray-200 rounded-lg focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 outline-none transition-all">
                            </div>
                            <select x-model="adjustCategory" class="h-9 px-3 text-sm bg-white border border-gray-200 rounded-lg focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 outline-none">
                                <option value="all">All Categories</option>
                                <template x-for="cat in rawMaterialCategories" :key="cat"><option :value="cat" x-text="cat"></option></template>
                            </select>
                        </div>
                    </div>

                    {{-- Table --}}
                    <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 z-10">
                                <tr class="border-b border-gray-100 bg-white">
                                    <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Material</th>
                                    <th class="text-right px-2 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Current</th>
                                    <th class="text-left px-2 py-2.5 text-xs font-semibold text-amber-600 uppercase tracking-wider w-20 sm:w-24">Qty</th>
                                    <th class="text-left px-2 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider w-24 sm:w-32">Reason</th>
                                    <th class="text-right px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">New Stock</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <template x-for="m in adjustList" :key="m.id">
                                    <tr class="transition-colors" :class="adjustOverflow(m) ? 'bg-red-50/60' : (adjustQty(m.id) > 0 ? (adjustIsBalance(m.id) ? 'bg-blue-50/40' : 'bg-amber-50/40') : 'hover:bg-gray-50/50')">
                                        <td class="px-5 py-2.5">
                                            <div class="flex items-center gap-2.5">
                                                <span class="text-lg flex-shrink-0" x-text="m.image"></span>
                                                <div class="min-w-0">
                                                    <p class="font-medium text-gray-900 truncate" x-text="m.name"></p>
                                                    <p class="text-[11px] text-gray-400 truncate"><span class="font-mono" x-text="m.sku"></span> &middot; <span x-text="m.category"></span></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-2 py-2.5 text-right whitespace-nowrap"><span class="font-medium text-gray-700" x-text="parseFloat(m.currentQty).toFixed(2).replace(/\.00$/, '')"></span> <span class="text-[11px] text-gray-400" x-text="m.unit"></span></td>
                                        <td class="px-2 py-2.5">
                                            <div class="relative">
                                                <input type="number" min="0" step="0.01" x-model="adjustLines[m.id]" placeholder="0" class="w-full h-9 pl-2 pr-7 text-sm text-right bg-white border rounded-lg outline-none transition-all focus:ring-2" :class="adjustOverflow(m) ? 'border-red-400 focus:border-red-500 focus:ring-red-500/20' : (adjustQty(m.id) > 0 ? (adjustIsBalance(m.id) ? 'border-blue-400 focus:border-blue-500 focus:ring-blue-500/20' : 'border-amber-400 focus:border-amber-500 focus:ring-amber-500/20') : 'border-gray-200 focus:border-amber-500 focus:ring-amber-500/20')">
                                                <span class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] text-gray-400 pointer-events-none" x-text="m.unit"></span>
                                            </div>
                                        </td>
                                        <td class="px-2 py-2.5">
                                            <div class="relative">
                                                <select x-model="adjustReasons[m.id]"
                                                        class="w-full h-9 pl-2 pr-7 text-xs font-medium bg-white border rounded-lg outline-none transition-all focus:ring-2 appearance-none cursor-pointer"
                                                        :class="adjustReasonFor(m.id).tone === 'red' ? 'border-red-300 text-red-700 focus:border-red-500 focus:ring-red-500/20' : (adjustReasonFor(m.id).tone === 'amber' ? 'border-amber-300 text-amber-700 focus:border-amber-500 focus:ring-amber-500/20' : 'border-blue-300 text-blue-700 focus:border-blue-500 focus:ring-blue-500/20')">
                                                    <template x-for="r in adjustReasonOptions" :key="r.id">
                                                        <option :value="r.id" x-text="r.label"></option>
                                                    </template>
                                                </select>
                                                <span class="absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400">@heroicon('o-chevron-down', 'w-3.5 h-3.5')</span>
                                            </div>
                                        </td>
                                        <td class="px-5 py-2.5 text-right whitespace-nowrap">
                                            <span class="font-bold" :class="adjustOverflow(m) ? 'text-red-600' : (adjustQty(m.id) > 0 ? (adjustIsBalance(m.id) ? 'text-blue-600' : 'text-amber-600') : 'text-gray-400')" x-text="adjustNewQty(m)"></span>
                                            <span class="text-[11px] text-gray-400" x-text="m.unit"></span>
                                            <span class="block text-[10px] font-medium text-red-600" x-show="adjustOverflow(m)">Exceeds current stock</span>
                                            <span class="block text-[10px] text-gray-500"
                                                  x-show="!adjustOverflow(m) && adjustQty(m.id) > 0"
                                                  :class="adjustIsBalance(m.id) && adjustNewQty(m) >= m.currentQty ? 'text-emerald-600' : 'text-red-600'"
                                                  x-text="(adjustIsBalance(m.id)
                                                              ? (adjustNewQty(m) >= m.currentQty ? '+' : '') + BmsMoney.symbol + ' ' + formatMoney((adjustNewQty(m) - m.currentQty) * m.costPerUnit)
                                                              : '- ' + BmsMoney.symbol + ' ' + formatMoney(adjustQty(m.id) * m.costPerUnit))"></span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        <div x-show="adjustList.length === 0" class="py-12 text-center text-gray-400 text-sm">No raw materials match your search.</div>
                    </div>

                    {{-- Footer --}}
                    <div class="px-5 py-3.5 bg-gray-50 border-t border-gray-200 flex-shrink-0">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs min-w-0">
                                <template x-for="r in adjustReasonSummary" :key="r.id">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold ring-1 whitespace-nowrap" :class="rmToneClass(r.tone)" x-text="r.label + ' × ' + r.count"></span>
                                </template>
                                <span class="text-gray-500 whitespace-nowrap">Items: <span class="font-bold text-gray-900" x-text="adjustLineCount"></span></span>
                                <span class="text-gray-500 whitespace-nowrap">Total qty: <span class="font-bold text-gray-900" x-text="parseFloat(adjustTotalQty.toFixed(2))"></span></span>
                                <span class="text-gray-500 whitespace-nowrap">Value impact:
                                    <span class="font-bold" :class="adjustValueImpact < 0 ? 'text-red-600' : (adjustValueImpact > 0 ? 'text-emerald-600' : 'text-gray-900')"
                                          x-text="(adjustValueImpact < 0 ? '- ' : (adjustValueImpact > 0 ? '+ ' : '')) + BmsMoney.symbol + ' ' + formatMoney(Math.abs(adjustValueImpact))"></span>
                                </span>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <button @click="closeAdjustModal()" class="flex-1 sm:flex-none px-4 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white transition-colors">Cancel</button>
                                <button @click="saveAdjust()" :disabled="materialSaving === 'adjust'" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-amber-500 text-sm font-semibold text-white hover:bg-amber-600 transition-colors shadow-sm shadow-amber-200 whitespace-nowrap disabled:opacity-60">
                                    <span x-show="materialSaving === 'adjust'" class="bms-progress-ring"></span>
                                    <span x-show="materialSaving !== 'adjust'">@heroicon('o-check', 'w-4 h-4')</span>
                                    <span x-text="materialSaving === 'adjust' ? 'Saving...' : 'Save Adjustment'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
        @endif
    </div>

    {{-- RECIPES TAB --}}
    <div x-show="activeTab === 'recipes'" x-cloak>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
            <div>
                <h2 class="text-lg font-bold text-gray-900">Recipes / Bill of Materials</h2>
                <p class="mt-1 text-sm text-gray-500">Define ingredients, costs, and preparation methods for each food item.</p>
            </div>
            <div class="flex items-center gap-3 mt-4 sm:mt-0">
                @if ($canRecipeAdd)
                <button @click="openAddRecipe()" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-blue-600 text-white text-sm font-semibold rounded-xl hover:bg-blue-700 transition-colors shadow-sm shadow-blue-200">
                    @heroicon('o-plus', 'w-4 h-4')
                    Add Recipe
                </button>
                @endif
            </div>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
            <div class="relative flex-1 max-w-xs">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" x-model.debounce.300ms="recipeSearchQuery" placeholder="Search recipes..." class="w-full h-9 pl-9 pr-4 text-sm bg-white border border-gray-200 rounded-lg focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
            </div>
            <div class="flex items-center gap-2 overflow-x-auto no-scrollbar">
                <button @click="recipeCategoryFilter = 'all'" :class="recipeCategoryFilter === 'all' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all whitespace-nowrap">All</button>
                <template x-for="cat in recipeCategories" :key="cat">
                    <button @click="recipeCategoryFilter = cat" :class="recipeCategoryFilter === cat ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all whitespace-nowrap" x-text="cat"></button>
                </template>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <template x-for="r in filteredRecipes" :key="r.id">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden hover:shadow-md transition-all duration-200">
                    <div class="p-5">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <h3 class="text-sm font-bold text-gray-900" x-text="r.name"></h3>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-[10px] font-medium text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded" x-text="r.code"></span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-blue-50 text-blue-700 ring-1 ring-blue-600/20" x-text="r.category"></span>
                                    <span class="text-[10px] text-gray-400">v<span x-text="r.version"></span></span>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-1 mb-3">
                            <template x-for="(ing, idx) in r.ingredients.slice(0, 4)" :key="idx">
                                <div class="flex items-center justify-between text-xs text-gray-500">
                                    <span x-text="ing.name"></span>
                                    <span x-text="ing.qty + ' ' + ing.unit"></span>
                                </div>
                            </template>
                            <template x-if="r.ingredients.length > 4"><p class="text-xs text-gray-400" x-text="'+' + (r.ingredients.length - 4) + ' more ingredients'"></p></template>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3 space-y-1">
                            <div class="flex justify-between text-xs"><span class="text-gray-500">Production Price</span><span class="font-bold text-gray-900"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(recipeTotalCost(r))"></span></span></div>
                            <div class="flex justify-between text-xs"><span class="text-gray-500">Cost / Serving</span><span class="font-medium text-gray-900"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(recipeCostPerServing(r))"></span></span></div>
                            <div class="flex justify-between text-xs"><span class="text-gray-500">Output</span><span class="font-medium text-gray-900" x-text="r.outputQty + ' ' + r.outputUnit"></span></div>
                        </div>
                        <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100">
                            <div class="flex items-center gap-3 text-xs text-gray-500">
                                <span class="flex items-center gap-1">@heroicon('o-cube', 'w-3.5 h-3.5') <span x-text="r.ingredients.length + ' ingredients'"></span></span>
                            </div>
                            @if ($canRecipeEdit || $canRecipeDelete)
                            <div class="flex items-center gap-1">
                                @if ($canRecipeEdit)
                                <button @click="openEditRecipe(r)" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Edit Recipe">@heroicon('o-pencil', 'w-4 h-4')</button>
                                @endif
                                @if ($canRecipeDelete)
                                <button @click="openDeleteRecipe(r)" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete Recipe">@heroicon('o-trash', 'w-4 h-4')</button>
                                @endif
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </template>
        </div>

        @if ($canRecipeAdd || $canRecipeEdit)
        {{-- Add Recipe Modal --}}
        <template x-teleport="body">
            <div x-show="showAddRecipe" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.away="showAddRecipe = false" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showAddRecipe = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl z-10 overflow-hidden max-h-[85vh] overflow-y-auto" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                    <div class="sticky top-0 bg-white flex items-center justify-between px-6 py-4 border-b border-gray-200 z-10">
                        <h3 class="text-lg font-bold text-gray-900" x-text="recipeFormMode === 'edit' ? 'Edit Recipe' : 'Add Recipe'"></h3>
                        <button @click="showAddRecipe = false" class="p-1 text-gray-400 hover:text-gray-600 rounded-lg">@heroicon('o-x-mark', 'w-5 h-5')</button>
                    </div>
                    <div class="px-6 py-5 space-y-5">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900 mb-3">Basic Information</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1.5">Recipe Name</label><input type="text" x-model="newRecipe.name" placeholder="e.g. Chicken Burger" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"></div>
                                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Recipe Code</label><input type="text" x-model="newRecipe.code" placeholder="Auto-generated" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"></div>
                                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Category</label><select x-model="newRecipe.category" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none"><template x-for="cat in recipeCategories" :key="cat"><option :value="cat" x-text="cat"></option></template></select></div>
                                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Output Quantity</label><input type="number" x-model="newRecipe.outputQty" placeholder="1" min="1" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"></div>
                                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Output Unit</label><select x-model="newRecipe.outputUnit" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none"><template x-for="u in recipeUnits" :key="u"><option :value="u" x-text="u"></option></template></select></div>
                                <div class="col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1.5">Production Price (<span x-text="BmsMoney.symbol"></span>)</label><input type="text" readonly :value="BmsMoney.symbol + ' ' + formatMoney(newRecipeIngredients.reduce(function(s, ing){ return s + (parseFloat(ing.totalCost) || 0); }, 0))" class="w-full h-10 px-4 text-sm bg-gray-100 border border-gray-300 rounded-xl text-gray-700 outline-none cursor-not-allowed" title="Auto-calculated as the sum of all ingredients listed below"></div>
                                <div class="col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1.5">Instructions</label><textarea x-model="newRecipe.instructions" rows="2" placeholder="Preparation instructions..." class="w-full px-4 py-2.5 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all resize-none"></textarea></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-sm font-semibold text-gray-900">Ingredients</h4>
                                <button @click="addRecipeIngredient()" class="text-xs text-blue-600 hover:text-blue-700 font-medium flex items-center gap-1">@heroicon('o-plus', 'w-3.5 h-3.5') Add Ingredient</button>
                            </div>
                            <div class="space-y-2">
                                <template x-for="(ing, idx) in newRecipeIngredients" :key="idx">
                                    <div class="flex items-center gap-2 bg-gray-50 rounded-xl p-2.5">
                                        <select x-model="ing.materialId" @change="updateRecipeIngredientCost(ing)" class="flex-1 h-8 px-2 text-xs bg-white border border-gray-200 rounded-lg focus:border-blue-500 outline-none">
                                            <option value="">Select...</option>
                                            <template x-for="m in rawMaterials" :key="m.id"><option :value="m.id" x-text="m.name"></option></template>
                                        </select>
                                        <input type="number" x-model="ing.qty" @input="updateRecipeIngredientCost(ing)" placeholder="Qty" min="0" step="0.001" class="w-20 h-8 px-2 text-xs bg-white border border-gray-200 rounded-lg focus:border-blue-500 outline-none text-right">
                                        <span class="text-xs text-gray-500 w-8" x-text="ing.unit"></span>
                                        <span class="text-xs font-medium text-gray-900 w-20 text-right"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(ing.totalCost)"></span></span>
                                        <button @click="removeRecipeIngredient(idx)" class="p-1 text-gray-400 hover:text-red-500 rounded">@heroicon('o-x-mark', 'w-3.5 h-3.5')</button>
                                    </div>
                                </template>
                            </div>
                            <template x-if="newRecipeIngredients.length === 0"><div class="text-center py-6 text-gray-400 text-sm"><p>No ingredients added yet. Click "Add Ingredient" to start.</p></div></template>
                            <div class="mt-3 flex items-center justify-between px-4 py-3 bg-blue-50 border border-blue-100 rounded-xl">
                                <span class="text-sm font-medium text-blue-700">Production Price (Total)</span>
                                <span class="text-base font-bold text-blue-700"><span x-text="BmsMoney.symbol"></span> <span x-text="formatMoney(newRecipeIngredients.reduce(function(s, i){ return s + (parseFloat(i.totalCost) || 0); }, 0))"></span></span>
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex gap-2">
                        <button @click="showAddRecipe = false" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white transition-colors">Cancel</button>
                        <button x-show="recipeFormMode === 'edit' ? canEditRecipe : canAddRecipe" @click="recipeFormMode === 'edit' ? saveEditRecipe() : addRecipe()" :disabled="materialSaving === 'recipe'" class="flex-1 inline-flex items-center justify-center gap-2 py-2.5 rounded-xl bg-blue-600 text-sm font-semibold text-white hover:bg-blue-700 transition-colors shadow-sm shadow-blue-200 disabled:opacity-60"><span x-show="materialSaving === 'recipe'" class="bms-progress-ring"></span><span x-text="materialSaving === 'recipe' ? 'Saving...' : (recipeFormMode === 'edit' ? 'Save Changes' : 'Create Recipe')"></span></button>
                    </div>
                </div>
            </div>
        </template>
        @endif

        @if ($canRecipeDelete)
        {{-- Delete Recipe Confirm Modal --}}
        <template x-teleport="body">
            <div x-show="showDeleteRecipe" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.away="showDeleteRecipe = false" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showDeleteRecipe = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md z-10 overflow-hidden" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                    <div class="p-6 text-center">
                        <div class="mx-auto w-14 h-14 rounded-full bg-red-50 flex items-center justify-center mb-4">
                            @heroicon('o-exclamation-triangle', 'w-7 h-7 text-red-600')
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 mb-1">Delete Recipe?</h3>
                        <template x-if="recipeToDelete">
                            <p class="text-sm text-gray-500">You are about to permanently delete <span class="font-semibold text-gray-900" x-text="recipeToDelete.name"></span>. Food items linked to this recipe will lose their production details.</p>
                        </template>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex gap-2">
                        <button @click="showDeleteRecipe = false" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white transition-colors">Cancel</button>
                        <button @click="confirmDeleteRecipe()" :disabled="materialSaving === 'recipe-delete'" class="flex-1 inline-flex items-center justify-center gap-2 py-2.5 rounded-xl bg-red-600 text-sm font-semibold text-white hover:bg-red-700 transition-colors shadow-sm shadow-red-200 disabled:opacity-60"><span x-show="materialSaving === 'recipe-delete'" class="bms-progress-ring"></span><span x-text="materialSaving === 'recipe-delete' ? 'Deleting...' : 'Delete'"></span></button>
                    </div>
                </div>
            </div>
        </template>
        @endif
    </div>
</div>
@endsection