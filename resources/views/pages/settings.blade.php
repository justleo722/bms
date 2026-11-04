@extends('layouts.master', ['activeNav' => $activeNav ?? 'settings'])

@section('title', 'Settings')

@push('head')
<style>
.stg-scroll::-webkit-scrollbar { width: 4px; height: 4px; }
.stg-scroll::-webkit-scrollbar-track { background: transparent; }
.stg-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.bms-progress-ring { width: 1rem; height: 1rem; border: 2px solid currentColor; border-right-color: transparent; border-radius: 9999px; animation: bms-progress-spin .7s linear infinite; }
@keyframes bms-progress-spin { to { transform: rotate(360deg); } }
</style>
<script>
window.defaultPaymentModes = function () {
    return [
        { id: 1, name: 'Cash', enabled: true, requiresRef: false, refLabel: '', icon: '💵' },
        { id: 2, name: 'Card (Visa/Mastercard)', enabled: true, requiresRef: true, refLabel: 'Approval code', icon: '💳' },
        { id: 3, name: 'Mobile Money (M-Pesa, Tigo, Airtel)', enabled: true, requiresRef: true, refLabel: 'Transaction / phone no.', icon: '📱' },
        { id: 4, name: 'Bank Transfer', enabled: false, requiresRef: true, refLabel: 'Reference / slip no.', icon: '🏦' },
        { id: 5, name: 'Credit (House Account)', enabled: false, requiresRef: false, refLabel: '', icon: '🧾' },
    ];
};
window.paymentModeIcons = ['💵', '💳', '📱', '🏦', '🧾', '🎟️', '💎', '🪙', '🏷️', '📶'];
</script>
<script>
window.COUNTERS_KEY = 'bms.counters';
window.defaultCounters = function () {
    return [
        { name: 'Main Counter', products: 18, stockValue: 3240000 },
        { name: 'VIP Counter', products: 12, stockValue: 2180000 },
        { name: 'Pool Bar', products: 10, stockValue: 1560000 },
        { name: 'Lounge Bar', products: 11, stockValue: 1840000 },
    ];
};
window.readCounters = function () {
    try {
        var raw = localStorage.getItem(window.COUNTERS_KEY);
        if (raw) {
            var parsed = JSON.parse(raw);
            if (Array.isArray(parsed)) return parsed;
        }
    } catch (e) {}
    return window.defaultCounters();
};
window.writeCounters = function (list) {
    try { localStorage.setItem(window.COUNTERS_KEY, JSON.stringify(list)); } catch (e) {}
};

</script>
<script>
window.BMS_CURRENCY_GROUPS = @json($currencyGroups);

window.settingsData = function () {
    return {
        activeSection: 'bar',
        trashItems: window.BmsTrash.read().concat(@json($inventoryTrash)),
        trashError: '',
        trashSavingId: '',
        roomsEnabled: window.BmsRooms.enabled(),

        // ========== BAR PROFILE (persisted on the server) ==========
        bar: {
            name: @json($settings['name']),
            phone: @json($settings['phone']),
            address: @json($settings['address']),
            receipt_header: @json($settings['receipt_header']),
            receipt_footer: @json($settings['receipt_footer']),
            currency: @json($settings['currency']),
        },
        savedBar: null,
        saving: false,
        saveNotice: '',
        saveError: '',

        // ========== LOGO ==========
        logoUrl: @json(\App\Settings\BarSettings::logoUrl()),
        logoName: '',
        logoSize: 0,
        logoUploading: false,
        logoError: '',
        logoNotice: '',

        // ========== CONFIRMATION DIALOG ==========
        confirmState: {
            open: false,
            title: 'Delete',
            message: 'Are you sure you want to delete',
            item: '',
            confirmLabel: 'Delete',
            variant: 'danger',
            onConfirm: null,
        },

        init() {
            this.savedBar = Object.assign({}, this.bar);
            window.writeCounters(this.counters);
            window.BmsUnits.write(this.units);
        },

        // ---------- Currency helpers ----------
        currencyList() {
            var out = [];
            var groups = window.BMS_CURRENCY_GROUPS || {};
            Object.keys(groups).forEach(function (region) {
                (groups[region] || []).forEach(function (c) {
                    out.push({ region: region, code: c.code, symbol: c.symbol, name: c.name, decimals: c.decimals });
                });
            });
            return out;
        },
        currencySymbol(code) {
            var found = this.currencyList().filter(function (c) { return c.code === code; })[0];
            return found ? found.symbol : 'TSh';
        },
        currencyDecimals(code) {
            var found = this.currencyList().filter(function (c) { return c.code === code; })[0];
            return found ? found.decimals : 2;
        },
        currencySample() {
            var decimals = this.currencyDecimals(this.bar.currency);
            var amount = (12500.5).toFixed(decimals);
            var parts = amount.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            return this.currencySymbol(this.bar.currency) + ' ' + parts.join('.');
        },

        // ---------- Saving the bar profile ----------
        saveSettings() {
            var self = this;
            this.saving = true;
            this.saveError = '';
            this.saveNotice = '';

            window.axios.post(@json(route('settings.update')), this.bar, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function (res) {
                    self.savedBar = Object.assign({}, self.bar);
                    self.saveNotice = (res.data && res.data.message) || 'Settings saved successfully.';
                    self.applyCurrency();
                    self.clearNoticeLater();
                })
                .catch(function (err) {
                    var data = err.response && err.response.data;
                    if (data && data.errors) {
                        self.saveError = Object.keys(data.errors).map(function (k) { return data.errors[k][0]; }).join(' ');
                    } else if (data && data.message) {
                        self.saveError = data.message;
                    } else {
                        self.saveError = 'Could not save the settings. Please try again.';
                    }
                })
                .then(function () { self.saving = false; });
        },
        cancelSettings() {
            this.bar = Object.assign({}, this.savedBar || {});
            this.saveError = '';
            this.saveNotice = '';
        },
        applyCurrency() {
            if (window.BmsMoney) {
                window.BmsMoney.symbol = this.currencySymbol(this.bar.currency);
                window.BmsMoney.code = this.bar.currency;
                window.BmsMoney.decimals = this.currencyDecimals(this.bar.currency);
            }
        },
        clearNoticeLater() {
            var self = this;
            clearTimeout(this._noticeTimer);
            this._noticeTimer = setTimeout(function () { self.saveNotice = ''; }, 4000);
        },
        csrfToken() {
            var meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        },

        // ---------- Logo upload ----------
        pickLogo(event) {
            var input = event.target;
            var file = input.files && input.files[0];
            if (!file) return;

            var self = this;
            this.logoError = '';
            this.logoNotice = '';

            var allowed = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp', 'image/svg+xml'];
            if (file.type && allowed.indexOf(file.type) === -1) {
                this.logoError = 'Please upload a PNG, JPG, WEBP or SVG image.';
                input.value = '';
                return;
            }
            if (file.size > (2 * 1024 * 1024)) {
                this.logoError = 'The logo must be 2MB or smaller.';
                input.value = '';
                return;
            }

            this.logoUploading = true;

            var data = new FormData();
            data.append('logo', file);

            window.axios.post(@json(route('settings.logo.upload')), data, {
                headers: { 'X-CSRF-TOKEN': self.csrfToken(), 'Content-Type': 'multipart/form-data' },
            })
                .then(function (res) {
                    self.logoUrl = res.data.url;
                    self.logoName = res.data.name;
                    self.logoSize = res.data.size;
                    self.logoNotice = res.data.message || 'Logo uploaded.';
                })
                .catch(function (err) {
                    var data = err.response && err.response.data;
                    self.logoError = (data && data.message) || 'The logo could not be uploaded. Please try again.';
                })
                .then(function () {
                    self.logoUploading = false;
                    input.value = '';
                });
        },
        removeLogo() {
            var self = this;
            this.logoError = '';
            this.logoNotice = '';

            window.axios.delete(@json(route('settings.logo.remove')), { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function (res) {
                    self.logoUrl = null;
                    self.logoName = '';
                    self.logoSize = 0;
                    self.logoNotice = (res.data && res.data.message) || 'Logo removed.';
                })
                .catch(function () {
                    self.logoError = 'The logo could not be removed. Please try again.';
                });
        },
        logoSizeLabel() {
            if (!this.logoSize) return '';
            if (this.logoSize < 1024) return this.logoSize + ' B';
            if (this.logoSize < 1024 * 1024) return Math.round(this.logoSize / 1024) + ' KB';
            return (this.logoSize / (1024 * 1024)).toFixed(1) + ' MB';
        },
        barInitials() {
            var name = (this.bar.name || '').trim();
            if (!name) return 'BL';
            var parts = name.split(/\s+/);
            var initials = parts.length > 1 ? parts[0].charAt(0) + parts[1].charAt(0) : name.slice(0, 2);
            return initials.toUpperCase();
        },

        // ---------- Confirmation dialog ----------
        askDelete(title, item, onConfirm, options) {
            var opts = options || {};
            this.confirmState.open = true;
            this.confirmState.title = title;
            this.confirmState.message = opts.message || 'Are you sure you want to delete';
            this.confirmState.item = item || '';
            this.confirmState.confirmLabel = opts.confirmLabel || 'Delete';
            this.confirmState.variant = opts.variant || 'danger';
            this.confirmState.onConfirm = onConfirm;
        },
        closeConfirm() {
            this.confirmState.open = false;
            this.confirmState.onConfirm = null;
        },
        runConfirm() {
            var action = this.confirmState.onConfirm;
            this.closeConfirm();
            if (typeof action === 'function') action();
        },

        newRawMatCat: '',
        newFoodCat: '',
        newRecipeCat: '',
        newUnit: '',
        unitError: '',
        unitSaving: false,
        units: @json($inventoryUnits),
        inventoryCategories: @json($inventoryCategories->map(fn ($category) => ['id' => $category->id, 'slug' => $category->slug, 'name' => $category->name])),
        inventoryCategoryInput: '',
        inventoryCategorySaving: false,
        inventoryCategoryError: '',
        inventoryCategoryErrorFor: '',
        rawMaterialCategories: @json($kitchenRawMaterialCategories->map(fn ($category) => ['id' => $category->id, 'name' => $category->name])),
        rawMaterialCategorySaving: false,
        rawMaterialCategoryError: '',
        foodItemCategories: window.BmsTrash.readCollection('bms.foodItemCategories', ['Starters', 'Mains', 'Burgers & Sandwiches', 'Sides', 'Desserts', 'Beverages']),
        recipeCategories: window.BmsTrash.readCollection('bms.recipeCategories', ['Burger', 'Pizza', 'Pasta', 'Salad', 'Dessert', 'Snack', 'Drinks']),
        addRawMatCat() {
            var name = (this.newRawMatCat || '').trim();
            this.rawMaterialCategoryError = '';
            if (!name) { this.rawMaterialCategoryError = 'Enter a category name.'; return; }
            if (this.rawMaterialCategories.some(function (category) { return category.name.toLowerCase() === name.toLowerCase(); })) {
                this.rawMaterialCategoryError = 'That raw material category already exists.';
                return;
            }
            var self = this;
            this.rawMaterialCategorySaving = true;
            window.axios.post(@json(route('settings.kitchen.categories.store')), { name: name }, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function (response) {
                    self.rawMaterialCategories.push(response.data.category);
                    self.newRawMatCat = '';
                })
                .catch(function (error) { self.rawMaterialCategoryError = self.apiError(error, 'Could not add the category.'); })
                .then(function () { self.rawMaterialCategorySaving = false; });
        },
        removeRawMatCat(id) {
            var self = this;
            this.rawMaterialCategoryError = '';
            this.rawMaterialCategorySaving = true;
            window.axios.delete(@json(url('/settings/kitchen/raw-material-categories')) + '/' + id, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function () {
                    self.rawMaterialCategories = self.rawMaterialCategories.filter(function (category) { return category.id !== id; });
                })
                .catch(function (error) { self.rawMaterialCategoryError = self.apiError(error, 'Could not delete the category.'); })
                .then(function () { self.rawMaterialCategorySaving = false; });
        },
        addFoodCat() { this.addCat(this.foodItemCategories, this.newFoodCat); window.BmsTrash.writeCollection('bms.foodItemCategories', this.foodItemCategories); this.newFoodCat = ''; },
        removeFoodCat(i) { var item = this.foodItemCategories[i]; this.foodItemCategories.splice(i, 1); window.BmsTrash.writeCollection('bms.foodItemCategories', this.foodItemCategories); this.moveToTrash('Food category', item, item, 'bms.foodItemCategories'); },
        addRecipeCat() { this.addCat(this.recipeCategories, this.newRecipeCat); window.BmsTrash.writeCollection('bms.recipeCategories', this.recipeCategories); this.newRecipeCat = ''; },
        removeRecipeCat(i) { var item = this.recipeCategories[i]; this.recipeCategories.splice(i, 1); window.BmsTrash.writeCollection('bms.recipeCategories', this.recipeCategories); this.moveToTrash('Recipe category', item, item, 'bms.recipeCategories'); },
        addUnit() {
            var value = (this.newUnit || '').trim();
            this.unitError = '';
            if (!value) { this.unitError = 'Enter a unit name.'; return; }
            var exists = this.units.some(function (unit) {
                return unit.toLowerCase() === value.toLowerCase();
            });
            if (exists) { this.unitError = 'A unit named "' + value + '" already exists.'; return; }
            var self = this;
            this.unitSaving = true;
            window.axios.post(@json(route('settings.inventory.units.store')), { name: value }, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function (res) {
                    self.units.push(res.data.unit);
                    window.BmsUnits.write(self.units);
                    self.newUnit = '';
                })
                .catch(function (err) { self.unitError = self.apiError(err, 'Could not add the unit.'); })
                .then(function () { self.unitSaving = false; });
        },
        removeUnit(i) {
            var item = this.units[i];
            var self = this;
            this.unitSaving = true;
            window.axios.delete(@json(route('settings.inventory.units.destroy')), {
                headers: { 'X-CSRF-TOKEN': self.csrfToken() },
                data: { name: item },
            })
                .then(function () {
                    self.units.splice(i, 1);
                    window.BmsUnits.write(self.units);
                })
                .catch(function (err) { self.unitError = self.apiError(err, 'Could not delete the unit.'); })
                .then(function () { self.unitSaving = false; });
        },
        addCat(list, value) {
            var v = (value || '').trim();
            if (!v || list.indexOf(v) !== -1) return;
            list.push(v);
        },

        // ========== COUNTERS ==========
        counters: @json($counters),
        newCounter: '',
        counterError: '',
        counterSaving: false,
        addCounter() {
            var name = (this.newCounter || '').trim();
            this.counterError = '';
            if (!name) { this.counterError = 'Enter a counter name.'; return; }
            var exists = this.counters.some(function (c) { return c.name.toLowerCase() === name.toLowerCase(); });
            if (exists) { this.counterError = 'A counter named "' + name + '" already exists.'; return; }
            var self = this;
            this.counterSaving = true;
            window.axios.post(@json(route('settings.inventory.counters.store')), { name: name }, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function (res) {
                    self.counters.push(res.data.counter);
                    window.writeCounters(self.counters);
                    self.newCounter = '';
                })
                .catch(function (err) {
                    self.counterError = self.apiError(err, 'Could not add the counter.');
                })
                .then(function () { self.counterSaving = false; });
        },
        removeCounter(id) {
            var self = this;
            this.counterError = '';
            this.counterSaving = true;
            window.axios.delete(@json(url('/settings/inventory/counters')) + '/' + id, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function () {
                    self.counters = self.counters.filter(function (counter) { return counter.id !== id; });
                    window.writeCounters(self.counters);
                })
                .catch(function (err) {
                    self.counterError = self.apiError(err, 'Could not delete the counter.');
                })
                .then(function () { self.counterSaving = false; });
        },
        counterInitial(name) {
            return (name || '?').slice(0, 2).toUpperCase();
        },
        addInventoryCategory() {
            var name = (this.inventoryCategoryInput || '').trim();
            this.inventoryCategoryError = '';
            if (!name) { this.inventoryCategoryError = 'Enter a category name.'; return; }
            if (this.inventoryCategories.some(function (category) { return category.name.toLowerCase() === name.toLowerCase(); })) {
                this.inventoryCategoryError = 'That inventory category already exists.';
                return;
            }
            var self = this;
            this.inventoryCategorySaving = true;
            window.axios.post(@json(route('settings.inventory.categories.store')), { name: name }, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function (res) {
                    self.inventoryCategories.push(res.data.category);
                    self.inventoryCategoryInput = '';
                })
                .catch(function (err) {
                    self.inventoryCategoryError = self.apiError(err, 'Could not add the category.');
                })
                .then(function () { self.inventoryCategorySaving = false; });
        },
        removeInventoryCategory(id) {
            var self = this;
            this.inventoryCategoryError = '';
            this.inventoryCategoryErrorFor = String(id);
            window.axios.delete(@json(url('/settings/inventory/categories')) + '/' + id, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function () {
                    self.inventoryCategories = self.inventoryCategories.filter(function (category) { return category.id !== id; });
                })
                .catch(function (err) {
                    self.inventoryCategoryError = self.apiError(err, 'Could not delete the category.');
                })
                .then(function () { self.inventoryCategoryErrorFor = ''; });
        },
        apiError(err, fallback) {
            var data = err.response && err.response.data;
            if (data && data.errors) return Object.keys(data.errors).map(function (key) { return data.errors[key][0]; }).join(' ');
            return data && data.message ? data.message : fallback;
        },

        // ========== TABLES ==========
        tables: @json($posTables),
        newTable: '',
        tableError: '',
        tableSaving: false,
        addTable() {
            var name = (this.newTable || '').trim();
            this.tableError = '';
            if (!name) { this.tableError = 'Enter a table name.'; return; }
            var exists = this.tables.some(function (t) { return t.name.toLowerCase() === name.toLowerCase(); });
            if (exists) { this.tableError = 'A table named "' + name + '" already exists.'; return; }
            var self = this;
            this.tableSaving = true;
            window.axios.post(@json(route('settings.tables.store')), { name: name }, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function (response) {
                    self.tables.push(response.data.table);
                    self.newTable = '';
                })
                .catch(function (error) { self.tableError = self.apiError(error, 'Could not add the table.'); })
                .then(function () { self.tableSaving = false; });
        },
        removeTable(id) {
            var self = this;
            this.tableError = '';
            this.tableSaving = true;
            window.axios.delete(@json(url('/settings/tables')) + '/' + id, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function () {
                    self.tables = self.tables.filter(function (table) { return table.id !== id; });
                })
                .catch(function (error) { self.tableError = self.apiError(error, 'Could not remove the table.'); })
                .then(function () { self.tableSaving = false; });
        },

        // ========== PAYMENT MODES ==========
        paymentModes: @json($posPaymentModes),
        paymentModeSaving: false,

        // ========== EXPENSE CATEGORIES ==========
        expenseCategories: @json($expenseCategories),
        newExpenseCat: '',
        expenseCategorySaving: false,
        expenseCategoryError: '',
        addExpenseCategory() {
            var v = (this.newExpenseCat || '').trim();
            if (!v || this.expenseCategorySaving) return;
            this.expenseCategoryError = '';
            this.expenseCategorySaving = true;
            var self = this;
            window.axios.post('/settings/expense-categories', { name: v }, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function (response) {
                    self.expenseCategories.push(response.data.category);
                    self.expenseCategories.sort(function (a, b) { return a.label.localeCompare(b.label); });
                    self.newExpenseCat = '';
                })
                .catch(function (error) { self.expenseCategoryError = self.apiError(error, 'Could not add the expense category.'); })
                .then(function () { self.expenseCategorySaving = false; });
        },
        removeExpenseCategory(category) {
            if (this.expenseCategorySaving) return;
            this.expenseCategoryError = '';
            this.expenseCategorySaving = true;
            var self = this;
            window.axios.delete('/settings/expense-categories/' + category.recordId, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function (response) {
                    self.expenseCategories = self.expenseCategories.filter(function (item) { return item.recordId !== category.recordId; });
                    self.trashItems.push({
                        id: 'expense-category-' + category.recordId,
                        type: 'Expense category',
                        label: category.label,
                        item: { recordId: category.recordId, slug: category.id },
                        sourceKey: 'server.expense-categories',
                        deletedAt: new Date().toISOString(),
                    });
                })
                .catch(function (error) { self.expenseCategoryError = self.apiError(error, 'Could not remove the expense category.'); })
                .then(function () { self.expenseCategorySaving = false; });
        },

        // ========== STAFF ROLES ==========
        staffRoles: @json($staffRoles),
        canManageStaffRoles: @json($canManageStaffRoles),
        newStaffRole: '',
        staffRoleError: '',
        staffRoleSaving: false,
        addStaffRole() {
            var label = (this.newStaffRole || '').trim();
            this.staffRoleError = '';
            if (!label || this.staffRoleSaving) return;
            this.staffRoleSaving = true;
            var self = this;
            window.axios.post('/settings/staff-roles', { name: label }, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function (response) {
                    self.staffRoles.push(response.data.role);
                    self.staffRoles.sort(function (a, b) { return a.label.localeCompare(b.label); });
                    self.newStaffRole = '';
                })
                .catch(function (error) { self.staffRoleError = self.apiError(error, 'Could not add the staff role.'); })
                .then(function () { self.staffRoleSaving = false; });
        },
        removeStaffRole(role) {
            if (this.staffRoleSaving) return;
            this.staffRoleSaving = true;
            var self = this;
            window.axios.delete('/settings/staff-roles/' + role.recordId, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function () {
                    self.staffRoles = self.staffRoles.filter(function (item) { return item.recordId !== role.recordId; });
                    self.trashItems.push({
                        id: 'staff-role-' + role.recordId,
                        type: 'Staff role',
                        label: role.label,
                        item: { recordId: role.recordId, slug: role.id },
                        sourceKey: 'server.staff-roles',
                        deletedAt: new Date().toISOString(),
                    });
                })
                .catch(function (error) { self.staffRoleError = self.apiError(error, 'Could not remove the staff role.'); })
                .then(function () { self.staffRoleSaving = false; });
        },
        staffRoleInitial(label) {
            return (label || '?').charAt(0).toUpperCase();
        },
        newPaymentMode: '',
        newPaymentIcon: '💵',
        newPaymentRef: false,
        newPaymentRefLabel: '',
        paymentModeError: '',
        paymentModeNotice: '',
        paymentModeSaved: false,
        get enabledPaymentModeCount() {
            return this.paymentModes.filter(function (m) { return m.enabled; }).length;
        },
        get nextPaymentModeId() {
            var max = 0;
            this.paymentModes.forEach(function (m) { if (m.id > max) max = m.id; });
            return max + 1;
        },
        nextPaymentIcon() {
            var used = {};
            this.paymentModes.forEach(function (m) { used[m.icon] = true; });
            var pool = window.paymentModeIcons.filter(function (i) { return !used[i]; });
            return pool.length ? pool[0] : window.paymentModeIcons[0];
        },
        persistPaymentModes() {
            var self = this;
            this.paymentModeError = '';
            this.paymentModeSaving = true;
            window.axios.put(@json(route('settings.payment-modes.update')), { modes: this.paymentModes }, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                .then(function (response) {
                    self.paymentModes = response.data.modes;
                    self.paymentModeSaved = true;
                    clearTimeout(self._pmTimer);
                    self._pmTimer = setTimeout(function () { self.paymentModeSaved = false; }, 2000);
                })
                .catch(function (error) { self.paymentModeError = self.apiError(error, 'Could not save payment modes.'); })
                .then(function () { self.paymentModeSaving = false; });
        },
        addPaymentMode() {
            var name = (this.newPaymentMode || '').trim();
            this.paymentModeError = '';
            if (!name) { this.paymentModeError = 'Enter a name for the payment mode.'; return; }
            var exists = this.paymentModes.some(function (m) { return m.name.toLowerCase() === name.toLowerCase(); });
            if (exists) { this.paymentModeError = 'A payment mode named "' + name + '" already exists.'; return; }
            this.paymentModes.push({
                id: null,
                name: name,
                enabled: true,
                requiresRef: this.newPaymentRef,
                refLabel: this.newPaymentRef ? ((this.newPaymentRefLabel || '').trim() || 'Reference') : '',
                icon: this.newPaymentIcon || this.nextPaymentIcon()
            });
            this.newPaymentMode = '';
            this.newPaymentRef = false;
            this.newPaymentRefLabel = '';
            this.newPaymentIcon = this.nextPaymentIcon();
            this.paymentModeNotice = '';
            this.persistPaymentModes();
        },
        removePaymentMode(i) {
            this.paymentModes.splice(i, 1);
            this.paymentModeNotice = this.enabledPaymentModeCount === 0
                ? 'No payment mode is active — POS will still save sales, but the docket will not show a payment method.'
                : '';
            this.persistPaymentModes();
        },
        togglePaymentMode(i) {
            var mode = this.paymentModes[i];
            mode.enabled = !mode.enabled;
            this.paymentModeNotice = this.enabledPaymentModeCount === 0
                ? 'No payment mode is active — POS will still save sales, but the docket will not show a payment method.'
                : '';
            this.persistPaymentModes();
        },
        restoreDefaultPaymentModes() {
            this.paymentModes = window.defaultPaymentModes().map(function (mode) {
                mode.id = null;
                return mode;
            });
            this.paymentModeError = '';
            this.paymentModeNotice = '';
            this.persistPaymentModes();
        },
        moveToTrash(type, label, item, sourceKey) {
            this.trashItems = window.BmsTrash.add(type, label, item, sourceKey);
        },
        restoreTrashItem(id) {
            var entry = this.trashItems.find(function (item) { return item.id === id; });
            if (entry && entry.sourceKey === 'server.inventory.products') {
                var self = this;
                if (this.trashSavingId) return;
                this.trashSavingId = id;
                this.trashError = '';
                window.axios.post(@json(url('/inventory/products')) + '/' + entry.item.productId + '/restore', {}, { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                    .then(function () {
                        self.trashItems = self.trashItems.filter(function (item) { return item.id !== id; });
                    })
                    .catch(function (err) { self.trashError = self.apiError(err, 'Could not restore the product.'); })
                    .then(function () { self.trashSavingId = ''; });
                return;
            }
            var kitchenTrashRoutes = {
                'server.kitchen.raw-materials': '/kitchen/raw-materials',
                'server.kitchen.recipes': '/kitchen/recipes',
                'server.kitchen.food-items': '/kitchen/food-items',
            };
            if (entry && kitchenTrashRoutes[entry.sourceKey]) {
                var kitchenSelf = this;
                if (this.trashSavingId) return;
                this.trashSavingId = id;
                this.trashError = '';
                window.axios.post(kitchenTrashRoutes[entry.sourceKey] + '/' + entry.item.recordId + '/restore', {}, { headers: { 'X-CSRF-TOKEN': kitchenSelf.csrfToken() } })
                    .then(function () {
                        kitchenSelf.trashItems = kitchenSelf.trashItems.filter(function (item) { return item.id !== id; });
                    })
                    .catch(function (error) { kitchenSelf.trashError = kitchenSelf.apiError(error, 'Could not restore the Kitchen record.'); })
                    .then(function () { kitchenSelf.trashSavingId = ''; });
                return;
            }
            if (entry && entry.sourceKey === 'server.expenses') {
                var expenseSelf = this;
                if (this.trashSavingId) return;
                this.trashSavingId = id;
                this.trashError = '';
                window.axios.post('/expenses/' + entry.item.recordId + '/restore', {}, { headers: { 'X-CSRF-TOKEN': expenseSelf.csrfToken() } })
                    .then(function () {
                        expenseSelf.trashItems = expenseSelf.trashItems.filter(function (item) { return item.id !== id; });
                    })
                    .catch(function (error) { expenseSelf.trashError = expenseSelf.apiError(error, 'Could not restore the expense.'); })
                    .then(function () { expenseSelf.trashSavingId = ''; });
                return;
            }
            if (entry && entry.sourceKey === 'server.expense-categories') {
                var categorySelf = this;
                if (this.trashSavingId) return;
                this.trashSavingId = id;
                this.trashError = '';
                window.axios.post('/settings/expense-categories/' + entry.item.recordId + '/restore', {}, { headers: { 'X-CSRF-TOKEN': categorySelf.csrfToken() } })
                    .then(function (response) {
                        categorySelf.expenseCategories.push(response.data.category);
                        categorySelf.expenseCategories.sort(function (a, b) { return a.label.localeCompare(b.label); });
                        categorySelf.trashItems = categorySelf.trashItems.filter(function (item) { return item.id !== id; });
                    })
                    .catch(function (error) { categorySelf.trashError = categorySelf.apiError(error, 'Could not restore the expense category.'); })
                    .then(function () { categorySelf.trashSavingId = ''; });
                return;
            }
            if (entry && entry.sourceKey === 'server.staff-roles') {
                var roleSelf = this;
                if (this.trashSavingId) return;
                this.trashSavingId = id;
                this.trashError = '';
                window.axios.post('/settings/staff-roles/' + entry.item.recordId + '/restore', {}, { headers: { 'X-CSRF-TOKEN': roleSelf.csrfToken() } })
                    .then(function (response) {
                        roleSelf.staffRoles.push(response.data.role);
                        roleSelf.staffRoles.sort(function (a, b) { return a.label.localeCompare(b.label); });
                        roleSelf.trashItems = roleSelf.trashItems.filter(function (item) { return item.id !== id; });
                    })
                    .catch(function (error) { roleSelf.trashError = roleSelf.apiError(error, 'Could not restore the staff role.'); })
                    .then(function () { roleSelf.trashSavingId = ''; });
                return;
            }
            if (entry && entry.sourceKey === 'server.staff') {
                var staffSelf = this;
                if (this.trashSavingId) return;
                this.trashSavingId = id;
                this.trashError = '';
                window.axios.post('/staff/' + entry.item.recordId + '/restore', {}, { headers: { 'X-CSRF-TOKEN': staffSelf.csrfToken() } })
                    .then(function () {
                        staffSelf.trashItems = staffSelf.trashItems.filter(function (item) { return item.id !== id; });
                    })
                    .catch(function (error) { staffSelf.trashError = staffSelf.apiError(error, 'Could not restore the staff member.'); })
                    .then(function () { staffSelf.trashSavingId = ''; });
                return;
            }
            if (!entry || !window.BmsTrash.restore(id)) return;

            var restored = window.BmsTrash.readCollection(entry.sourceKey, []);
            if (entry.sourceKey === 'bms.rawMaterialCategories') this.rawMaterialCategories = restored;
            if (entry.sourceKey === 'bms.foodItemCategories') this.foodItemCategories = restored;
            if (entry.sourceKey === 'bms.recipeCategories') this.recipeCategories = restored;
            if (entry.sourceKey === window.BmsUnits.key) this.units = restored;
            if (entry.sourceKey === window.COUNTERS_KEY) this.counters = restored;
            if (entry.sourceKey === 'bms.expenseCategories') this.expenseCategories = restored;
            this.trashItems = this.trashItems.filter(function (item) { return item.id !== id; });
        },
        permanentlyDeleteTrashItem(id) {
            var entry = this.trashItems.find(function (item) { return item.id === id; });
            if (entry && entry.sourceKey === 'server.inventory.products') {
                var self = this;
                if (this.trashSavingId) return;
                this.trashSavingId = id;
                this.trashError = '';
                window.axios.delete(@json(url('/inventory/products')) + '/' + entry.item.productId + '/permanent', { headers: { 'X-CSRF-TOKEN': self.csrfToken() } })
                    .then(function () {
                        self.trashItems = self.trashItems.filter(function (item) { return item.id !== id; });
                    })
                    .catch(function (err) { self.trashError = self.apiError(err, 'Could not permanently delete the product.'); })
                    .then(function () { self.trashSavingId = ''; });
                return;
            }
            var kitchenTrashRoutes = {
                'server.kitchen.raw-materials': '/kitchen/raw-materials',
                'server.kitchen.recipes': '/kitchen/recipes',
                'server.kitchen.food-items': '/kitchen/food-items',
            };
            if (entry && kitchenTrashRoutes[entry.sourceKey]) {
                var kitchenSelf = this;
                if (this.trashSavingId) return;
                this.trashSavingId = id;
                this.trashError = '';
                window.axios.delete(kitchenTrashRoutes[entry.sourceKey] + '/' + entry.item.recordId + '/permanent', { headers: { 'X-CSRF-TOKEN': kitchenSelf.csrfToken() } })
                    .then(function () {
                        kitchenSelf.trashItems = kitchenSelf.trashItems.filter(function (item) { return item.id !== id; });
                    })
                    .catch(function (error) { kitchenSelf.trashError = kitchenSelf.apiError(error, 'Could not permanently delete the Kitchen record.'); })
                    .then(function () { kitchenSelf.trashSavingId = ''; });
                return;
            }
            if (entry && entry.sourceKey === 'server.expenses') {
                var expenseSelf = this;
                if (this.trashSavingId) return;
                this.trashSavingId = id;
                this.trashError = '';
                window.axios.delete('/expenses/' + entry.item.recordId + '/permanent', { headers: { 'X-CSRF-TOKEN': expenseSelf.csrfToken() } })
                    .then(function () {
                        expenseSelf.trashItems = expenseSelf.trashItems.filter(function (item) { return item.id !== id; });
                    })
                    .catch(function (error) { expenseSelf.trashError = expenseSelf.apiError(error, 'Could not permanently delete the expense.'); })
                    .then(function () { expenseSelf.trashSavingId = ''; });
                return;
            }
            if (entry && entry.sourceKey === 'server.expense-categories') {
                var categorySelf = this;
                if (this.trashSavingId) return;
                this.trashSavingId = id;
                this.trashError = '';
                window.axios.delete('/settings/expense-categories/' + entry.item.recordId + '/permanent', { headers: { 'X-CSRF-TOKEN': categorySelf.csrfToken() } })
                    .then(function () {
                        categorySelf.trashItems = categorySelf.trashItems.filter(function (item) { return item.id !== id; });
                    })
                    .catch(function (error) { categorySelf.trashError = categorySelf.apiError(error, 'Could not permanently delete the expense category.'); })
                    .then(function () { categorySelf.trashSavingId = ''; });
                return;
            }
            if (entry && entry.sourceKey === 'server.staff-roles') {
                var roleSelf = this;
                if (this.trashSavingId) return;
                this.trashSavingId = id;
                this.trashError = '';
                window.axios.delete('/settings/staff-roles/' + entry.item.recordId + '/permanent', { headers: { 'X-CSRF-TOKEN': roleSelf.csrfToken() } })
                    .then(function () { roleSelf.trashItems = roleSelf.trashItems.filter(function (item) { return item.id !== id; }); })
                    .catch(function (error) { roleSelf.trashError = roleSelf.apiError(error, 'Could not permanently delete the staff role.'); })
                    .then(function () { roleSelf.trashSavingId = ''; });
                return;
            }
            if (entry && entry.sourceKey === 'server.staff') {
                var staffSelf = this;
                if (this.trashSavingId) return;
                this.trashSavingId = id;
                this.trashError = '';
                window.axios.delete('/staff/' + entry.item.recordId + '/permanent', { headers: { 'X-CSRF-TOKEN': staffSelf.csrfToken() } })
                    .then(function () { staffSelf.trashItems = staffSelf.trashItems.filter(function (item) { return item.id !== id; }); })
                    .catch(function (error) { staffSelf.trashError = staffSelf.apiError(error, 'Could not permanently delete the staff member.'); })
                    .then(function () { staffSelf.trashSavingId = ''; });
                return;
            }
            if (window.BmsTrash.permanentlyDelete(id)) {
                this.trashItems = this.trashItems.filter(function (item) { return item.id !== id; });
            }
        },
        setRoomsEnabled() {
            window.BmsRooms.setEnabled(this.roomsEnabled);
        },
    }
}
</script>
@endpush

@section('content')
<div x-data="settingsData()" class="pb-8 max-w-4xl mx-auto">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Settings</h1>
            <p class="mt-1 text-sm text-gray-500">Configure your bar and manage preferences</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" @click="cancelSettings()" class="h-10 px-5 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors">Cancel</button>
            <button type="button" @click="saveSettings()" :disabled="saving"
                    class="h-10 px-5 text-sm font-medium text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition-colors shadow-sm disabled:opacity-60 disabled:cursor-not-allowed inline-flex items-center gap-2">
                <svg x-show="saving" x-cloak class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                <span x-text="saving ? 'Saving...' : 'Save Changes'"></span>
            </button>
        </div>
    </div>

    {{-- Save feedback --}}
    <div x-show="saveNotice" x-cloak x-transition class="mb-5 flex items-start gap-2 px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-xs font-medium text-emerald-700">
        @heroicon('o-check-circle', 'w-4 h-4 flex-shrink-0 mt-px')
        <span x-text="saveNotice"></span>
    </div>
    <div x-show="saveError" x-cloak x-transition class="mb-5 flex items-start gap-2 px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-xs font-medium text-red-700">
        @heroicon('o-exclamation-circle', 'w-4 h-4 flex-shrink-0 mt-px')
        <span x-text="saveError"></span>
    </div>

    {{-- Section Tabs --}}
    <div class="flex flex-nowrap items-center gap-0 mb-6 overflow-x-auto no-scrollbar border-b border-gray-200">
        <button @click="activeSection = 'bar'" :class="activeSection === 'bar' ? 'border-b-2 border-blue-600 text-blue-600 font-semibold' : 'text-gray-500 hover:text-gray-700 border-b-2 border-transparent'" class="flex-shrink-0 px-2 py-3 text-xs font-medium transition-all -mb-px whitespace-nowrap">Bar Information</button>
        <button @click="activeSection = 'receipt'" :class="activeSection === 'receipt' ? 'border-b-2 border-blue-600 text-blue-600 font-semibold' : 'text-gray-500 hover:text-gray-700 border-b-2 border-transparent'" class="flex-shrink-0 px-2 py-3 text-xs font-medium transition-all -mb-px whitespace-nowrap">Receipt</button>
        <button @click="activeSection = 'counters'" :class="activeSection === 'counters' ? 'border-b-2 border-blue-600 text-blue-600 font-semibold' : 'text-gray-500 hover:text-gray-700 border-b-2 border-transparent'" class="flex-shrink-0 px-2 py-3 text-xs font-medium transition-all -mb-px whitespace-nowrap">Counters</button>
        <button @click="activeSection = 'tables'" :class="activeSection === 'tables' ? 'border-b-2 border-blue-600 text-blue-600 font-semibold' : 'text-gray-500 hover:text-gray-700 border-b-2 border-transparent'" class="flex-shrink-0 px-2 py-3 text-xs font-medium transition-all -mb-px whitespace-nowrap">Tables & Layout</button>
        <button @click="activeSection = 'categories'" :class="activeSection === 'categories' ? 'border-b-2 border-blue-600 text-blue-600 font-semibold' : 'text-gray-500 hover:text-gray-700 border-b-2 border-transparent'" class="flex-shrink-0 px-2 py-3 text-xs font-medium transition-all -mb-px whitespace-nowrap">Menu & Categories</button>
        <button x-show="canManageStaffRoles" @click="activeSection = 'roles'" :class="activeSection === 'roles' ? 'border-b-2 border-blue-600 text-blue-600 font-semibold' : 'text-gray-500 hover:text-gray-700 border-b-2 border-transparent'" class="flex-shrink-0 px-2 py-3 text-xs font-medium transition-all -mb-px whitespace-nowrap">Staff Roles</button>
        <button @click="activeSection = 'payment'" :class="activeSection === 'payment' ? 'border-b-2 border-blue-600 text-blue-600 font-semibold' : 'text-gray-500 hover:text-gray-700 border-b-2 border-transparent'" class="flex-shrink-0 px-2 py-3 text-xs font-medium transition-all -mb-px whitespace-nowrap">Payment Methods</button>
        <button @click="activeSection = 'trash'" :class="activeSection === 'trash' ? 'border-b-2 border-blue-600 text-blue-600 font-semibold' : 'text-gray-500 hover:text-gray-700 border-b-2 border-transparent'" class="flex-shrink-0 px-2 py-3 text-xs font-medium transition-all -mb-px whitespace-nowrap inline-flex items-center gap-1"><span class="flex-shrink-0">Trash</span><span class="flex-shrink-0 rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] leading-none" x-text="trashItems.length"></span></button>
    </div>

    <div class="space-y-8">

        {{-- ========== BAR INFORMATION ========== --}}
        <div x-show="activeSection === 'bar'" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-8 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                        @heroicon('o-building-storefront', 'w-5 h-5')
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Bar Information</h2>
                        <p class="text-xs text-gray-500">Update your bar's profile and preferences</p>
                    </div>
                </div>
            </div>
            <div class="px-8 py-6 space-y-6">
                <div>
                    <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3 block">Bar Logo</label>
                    <div class="flex items-center gap-6">
                        <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center text-white text-xl font-bold shadow-sm flex-shrink-0 overflow-hidden">
                            <template x-if="logoUrl">
                                <img :src="logoUrl" :alt="bar.name" class="w-full h-full object-cover">
                            </template>
                            <template x-if="!logoUrl">
                                <span x-text="barInitials()"></span>
                            </template>
                        </div>
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" @click="$refs.logoInput.click()"
                                        class="inline-flex items-center gap-1.5 h-9 px-4 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors">
                                    @heroicon('o-arrow-up-tray', 'w-4 h-4')
                                    <span x-text="logoUploading ? 'Uploading...' : (logoUrl ? 'Replace Logo' : 'Upload Logo')"></span>
                                </button>
                                <button x-show="logoUrl" x-cloak type="button" @click="askDelete('Remove Logo', bar.name, removeLogo, { variant: 'danger' })"
                                        class="inline-flex items-center gap-1.5 h-9 px-4 text-sm font-medium text-red-600 bg-white border border-red-200 rounded-xl hover:bg-red-50 transition-colors">
                                    @heroicon('o-trash', 'w-4 h-4')
                                    Remove
                                </button>
                                <input type="file" x-ref="logoInput" @change="pickLogo($event)"
                                       accept="image/png,image/jpeg,image/webp,image/svg+xml" class="hidden">
                            </div>
                            <p class="text-[11px] text-gray-400">PNG, JPG, WEBP or SVG. Max 2MB. 400x400px recommended.</p>
                            <p x-show="logoName" x-cloak class="text-[11px] text-gray-500">
                                <span x-text="logoName"></span>
                                <span x-show="logoSizeLabel()">&middot; <span x-text="logoSizeLabel()"></span></span>
                            </p>
                            <p x-show="logoUploading" x-cloak class="text-[11px] text-blue-600 font-medium">Uploading logo...</p>
                            <p x-show="logoError" x-cloak class="text-[11px] text-red-600 font-medium" x-text="logoError"></p>
                            <p x-show="logoNotice" x-cloak class="text-[11px] text-emerald-600 font-medium" x-text="logoNotice"></p>
                        </div>
                    </div>
                </div>
                <div class="h-px bg-gray-100"></div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Bar Name</label>
                        <input type="text" x-model="bar.name" placeholder="The Barefoot Lounge" class="w-full h-11 px-4 text-sm bg-white border border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Phone Number</label>
                        <input type="tel" x-model="bar.phone" placeholder="+255 123 456 789" class="w-full h-11 px-4 text-sm bg-white border border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Address</label>
                    <textarea rows="2" x-model="bar.address" placeholder="Kili Road, Moshi, Kilimanjaro, Tanzania" class="w-full px-4 py-3 text-sm bg-white border border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all resize-none"></textarea>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Rooms module</p>
                        <p class="mt-0.5 text-xs text-gray-500">Enable room and lodge management for this bar.</p>
                    </div>
                    <button type="button" role="switch" :aria-checked="roomsEnabled" @click="roomsEnabled = !roomsEnabled; setRoomsEnabled()"
                            :class="roomsEnabled ? 'bg-blue-600' : 'bg-gray-300'"
                            class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors">
                        <span class="sr-only">Enable rooms module</span>
                        <span :class="roomsEnabled ? 'translate-x-5' : 'translate-x-0.5'" class="inline-block h-5 w-5 transform rounded-full bg-white shadow-sm transition-transform"></span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ========== RECEIPT SETTINGS ========== --}}
        <div x-show="activeSection === 'receipt'" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-8 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        @heroicon('o-document-text', 'w-5 h-5')
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Receipt Settings</h2>
                        <p class="text-xs text-gray-500">Customize receipt appearance and currency</p>
                    </div>
                </div>
            </div>
            <div class="px-8 py-6 space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Receipt Header</label>
                        <input type="text" :value="bar.name" readonly class="w-full h-11 px-4 text-sm bg-gray-50 border border-gray-200 rounded-xl text-gray-600 outline-none">
                        <p class="mt-1 text-[11px] text-gray-400">This follows the Bar Name in Bar Information.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Receipt Footer</label>
                        <input type="text" x-model="bar.receipt_footer" placeholder="Thank you for visiting!" class="w-full h-11 px-4 text-sm bg-white border border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Currency</label>
                        <select x-model="bar.currency" class="w-full h-11 px-4 text-sm bg-white border border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all cursor-pointer">
                            @foreach ($currencyGroups as $region => $regionCurrencies)
                                <optgroup label="{{ $region }}">
                                    @foreach ($regionCurrencies as $option)
                                        <option value="{{ $option['code'] }}" @selected($option['code'] === $settings['currency'])>
                                            {{ $option['code'] }} ({{ $option['symbol'] }}) &mdash; {{ $option['name'] }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <p class="mt-1.5 text-[11px] text-gray-400">Used on printed dockets, bills and every money amount in the system.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Amount Preview</label>
                        <div class="w-full h-11 px-4 flex items-center text-sm bg-gray-50 border border-gray-200 rounded-xl text-gray-700 font-medium" x-text="currencySample()"></div>
                        <p class="mt-1.5 text-[11px] text-gray-400">How a line item amount will look on the receipt.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ========== COUNTERS ========== --}}
        <div x-show="activeSection === 'counters'" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-8 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                        @heroicon('o-building-storefront', 'w-5 h-5')
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Selling Counters</h2>
                        <p class="text-xs text-gray-500">Manage your POS selling counters and their stock</p>
                    </div>
                </div>
            </div>
            <div class="px-8 py-6 space-y-4">
                <div class="space-y-3">
                    <template x-for="counter in counters" :key="counter.id">
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center font-bold text-sm" x-text="counterInitial(counter.name)"></div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900" x-text="counter.name"></p>
                                    <p class="text-xs text-gray-500"><span x-text="counter.products"></span> products &middot; <span x-text="BmsMoney.symbol"></span> <span x-text="Number(counter.stockValue || 0).toLocaleString()"></span> stock value</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                <span class="text-xs font-medium text-emerald-600">Active</span>
                                <button @click="askDelete('Delete Counter', counter.name, () => removeCounter(counter.id))" :disabled="counterSaving" title="Delete counter" class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors disabled:opacity-50">
                                    <span x-show="counterSaving" class="bms-progress-ring"></span>
                                    <span x-show="!counterSaving">@heroicon('o-trash', 'w-4 h-4')</span>
                                </button>
                            </div>
                        </div>
                    </template>
                    <p x-show="!counters.length" class="py-6 text-center text-sm text-gray-400 bg-gray-50 border border-dashed border-gray-300 rounded-xl">No counters yet. Add your first one below.</p>
                </div>

                <div x-show="counterError" x-cloak class="flex items-start gap-2 px-3.5 py-2.5 rounded-xl bg-red-50 border border-red-200 text-xs font-medium text-red-700">
                    @heroicon('o-exclamation-circle', 'w-4 h-4 flex-shrink-0 mt-px')
                    <span x-text="counterError"></span>
                </div>

                <div class="inline-flex items-center rounded-xl border border-gray-300 overflow-hidden focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20 transition-all">
                    <input type="text" x-model="newCounter" @keydown.enter="addCounter()" placeholder="Add counter, e.g. Terrace Bar..." class="h-10 px-4 text-sm outline-none bg-white w-64">
                    <button @click="addCounter()" :disabled="counterSaving" class="h-10 px-3 bg-gray-50 text-blue-600 hover:bg-blue-50 transition-colors disabled:opacity-50">
                        <span x-show="counterSaving" class="bms-progress-ring inline-block"></span>
                        <span x-show="!counterSaving">@heroicon('o-plus', 'w-4 h-4')</span>
                    </button>
                </div>
                <p class="text-[11px] text-gray-400">Counters are inventory stock locations. Stock transfers and stock levels are tracked separately for each counter.</p>
            </div>
        </div>

        {{-- ========== TABLES & LAYOUT ========== --}}
        <div x-show="activeSection === 'tables'" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-8 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                        @heroicon('o-square-3-stack-3d', 'w-5 h-5')
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Tables & Layout</h2>
                        <p class="text-xs text-gray-500">Manage table positions for the POS table selector</p>
                    </div>
                </div>
            </div>
            <div class="px-8 py-6 space-y-4">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <template x-for="table in tables" :key="table.id">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                            <span class="text-sm font-medium text-gray-700" x-text="table.name"></span>
                            <button @click="askDelete('Delete Table', table.name, () => removeTable(table.id))" :disabled="tableSaving" title="Delete table" class="text-gray-400 hover:text-red-500 transition-colors disabled:opacity-50">
                                @heroicon('o-x-mark', 'w-4 h-4')
                            </button>
                        </div>
                    </template>
                </div>
                <p x-show="!tables.length" class="py-6 text-center text-sm text-gray-400 bg-gray-50 border border-dashed border-gray-300 rounded-xl">No tables yet. Add your first one below.</p>

                <div x-show="tableError" x-cloak class="flex items-start gap-2 px-3.5 py-2.5 rounded-xl bg-red-50 border border-red-200 text-xs font-medium text-red-700">
                    @heroicon('o-exclamation-circle', 'w-4 h-4 flex-shrink-0 mt-px')
                    <span x-text="tableError"></span>
                </div>

                <div class="inline-flex items-center rounded-xl border border-gray-300 overflow-hidden focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20 transition-all">
                    <input type="text" x-model="newTable" @keydown.enter="addTable()" placeholder="Add table, e.g. Garden 1..." class="h-10 px-4 text-sm outline-none bg-white w-64">
                    <button @click="addTable()" :disabled="tableSaving" class="h-10 px-3 bg-gray-50 text-blue-600 hover:bg-blue-50 transition-colors disabled:opacity-50">@heroicon('o-plus', 'w-4 h-4')</button>
                </div>
                <p class="text-[11px] text-gray-400">Tables appear in the POS table selector.</p>
            </div>
        </div>

        {{-- ========== PAYMENT METHODS ========== --}}
        <div x-show="activeSection === 'payment'" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-8 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-green-50 text-green-600 flex items-center justify-center">
                        @heroicon('o-credit-card', 'w-5 h-5')
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Payment Methods</h2>
                        <p class="text-xs text-gray-500">Create the payment modes the cashier can choose at POS checkout</p>
                    </div>
                    <div class="ml-auto flex items-center gap-2">
                        <span x-show="paymentModeSaved" x-cloak x-transition.opacity class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600">
                            @heroicon('o-check-circle', 'w-4 h-4')
                            Saved
                        </span>
                        <span class="text-xs font-medium text-gray-500 bg-gray-100 px-2.5 py-1 rounded-full">
                            <span x-text="enabledPaymentModeCount"></span> active of <span x-text="paymentModes.length"></span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="px-8 py-6 space-y-5">
                {{-- Active modes --}}
                <div class="space-y-2">
                    <template x-for="(mode, i) in paymentModes" :key="mode.id || mode.name">
                        <div class="flex items-center gap-3 p-4 rounded-xl border transition-colors"
                             :class="mode.enabled ? 'bg-emerald-50/40 border-emerald-200' : 'bg-gray-50 border-gray-200'">
                            <span class="w-10 h-10 rounded-lg bg-white border border-gray-200 text-lg flex items-center justify-center flex-shrink-0" x-text="mode.icon"></span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <p class="text-sm font-semibold text-gray-900" x-text="mode.name"></p>
                                    <span x-show="mode.requiresRef" class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-700">
                                        Ref required
                                    </span>
                                    <span x-show="mode.enabled" class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-700">Active</span>
                                    <span x-show="!mode.enabled" class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-gray-200 text-gray-600">Disabled</span>
                                </div>
                                <p class="text-xs text-gray-500" x-show="mode.requiresRef">
                                    POS asks for: <span class="font-medium text-gray-700" x-text="mode.refLabel || 'Reference'"></span>
                                </p>
                                <p class="text-xs text-gray-500" x-show="!mode.requiresRef">Shown on the POS payment screen and printed on the docket</p>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <button @click="togglePaymentMode(i)"
                                        :disabled="paymentModeSaving"
                                        :class="mode.enabled ? 'bg-emerald-500' : 'bg-gray-300'"
                                        :aria-label="mode.enabled ? 'Disable ' + mode.name : 'Enable ' + mode.name"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200">
                                    <span :class="mode.enabled ? 'translate-x-5' : 'translate-x-0.5'" class="inline-block h-5 w-5 transform rounded-full bg-white shadow-sm transition-transform duration-200"></span>
                                </button>
                                <button @click="askDelete('Remove Payment Mode', mode.name, () => removePaymentMode(i))" :disabled="paymentModeSaving" title="Remove payment mode" class="p-1.5 text-gray-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors disabled:opacity-50">
                                    @heroicon('o-trash', 'w-4 h-4')
                                </button>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 pl-1">
                            <label class="inline-flex items-center gap-2 text-xs text-gray-600">
                                <input type="checkbox" x-model="mode.requiresRef" @change="persistPaymentModes()" :disabled="paymentModeSaving" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                Require reference
                            </label>
                            <input x-show="mode.requiresRef" x-model="mode.refLabel" @change="persistPaymentModes()" :disabled="paymentModeSaving"
                                   placeholder="Reference label" class="h-8 w-48 rounded-lg border border-gray-300 px-2.5 text-xs outline-none focus:border-blue-500">
                        </div>
                    </template>

                    <div x-show="paymentModes.length === 0" class="py-8 text-center text-sm text-gray-400 bg-gray-50 border border-dashed border-gray-300 rounded-xl">
                        No payment modes yet. Add your first one below.
                    </div>
                </div>

                <div x-show="paymentModeError" x-cloak class="flex items-start gap-2 px-3.5 py-2.5 rounded-xl bg-red-50 border border-red-200 text-xs font-medium text-red-700">
                    @heroicon('o-exclamation-circle', 'w-4 h-4 flex-shrink-0 mt-px')
                    <span x-text="paymentModeError"></span>
                </div>

                <div x-show="paymentModeNotice" x-cloak class="flex items-start gap-2 px-3.5 py-2.5 rounded-xl bg-amber-50 border border-amber-200 text-xs font-medium text-amber-800">
                    @heroicon('o-information-circle', 'w-4 h-4 flex-shrink-0 mt-px')
                    <span x-text="paymentModeNotice"></span>
                </div>

                {{-- Add new --}}
                <div class="p-4 bg-gray-50 rounded-xl border border-gray-200">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Add Payment Mode</p>
                    <div class="grid grid-cols-1 sm:grid-cols-[auto_1fr] gap-3">
                        <div class="relative">
                            <select x-model="newPaymentIcon" class="h-10 pl-9 pr-2 text-sm bg-white border border-gray-300 rounded-xl outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 appearance-none cursor-pointer">
                                <template x-for="icon in (window.paymentModeIcons || [])" :key="icon">
                                    <option :value="icon" x-text="icon"></option>
                                </template>
                            </select>
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm pointer-events-none" x-text="newPaymentIcon"></span>
                            <span class="absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400">@heroicon('o-chevron-down', 'w-3.5 h-3.5')</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-3">
                            <input type="text" x-model="newPaymentMode" @keydown.enter="addPaymentMode()" :disabled="paymentModeSaving"
                                   placeholder="e.g. Credit (House Account), Card, Tigo Pesa..."
                                   class="h-10 px-3.5 text-sm bg-white border border-gray-300 rounded-xl outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all">
                            <button @click="addPaymentMode()" :disabled="paymentModeSaving" class="h-10 px-4 inline-flex items-center justify-center gap-1.5 rounded-xl bg-blue-600 text-sm font-semibold text-white hover:bg-blue-700 transition-colors shadow-sm shadow-blue-200 whitespace-nowrap disabled:opacity-50">
                                @heroicon('o-plus', 'w-4 h-4')
                                Add Mode
                            </button>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" x-model="newPaymentRef" class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="text-xs font-medium text-gray-600">Require a reference at POS</span>
                        </label>
                        <input type="text" x-model="newPaymentRefLabel" x-show="newPaymentRef" x-transition.opacity
                               placeholder="Label, e.g. Approval code / phone no."
                               class="h-8 px-3 text-xs bg-white border border-gray-300 rounded-lg outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all w-full sm:w-64">
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                    <p class="text-xs text-gray-500 flex items-center gap-1.5">
                        @heroicon('o-information-circle', 'w-4 h-4 text-gray-400')
                        Changes apply immediately. With none active, POS still saves sales and prints the docket without a payment method.
                    </p>
                    <button @click="askDelete('Restore Default Payment Modes', 'all custom payment modes will be replaced by the defaults', restoreDefaultPaymentModes, { confirmLabel: 'Restore', variant: 'warning' })" :disabled="paymentModeSaving" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50">
                        @heroicon('o-arrow-path', 'w-3.5 h-3.5')
                        Restore defaults
                    </button>
                </div>
            </div>
        </div>

        {{-- ========== TRASH ========== --}}
        <div x-show="activeSection === 'trash'" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-8 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-red-50 text-red-600 flex items-center justify-center">
                        @heroicon('o-trash', 'w-5 h-5')
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Trash</h2>
                        <p class="text-xs text-gray-500">Restore deleted records or permanently remove them.</p>
                    </div>
                    <span class="ml-auto text-xs font-medium text-gray-400 bg-gray-100 px-2.5 py-1 rounded-full" x-text="trashItems.length + ' items'"></span>
                </div>
            </div>
            <div class="px-8 py-6">
                <div x-show="trashError" x-cloak class="mb-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-xs font-medium text-red-700" x-text="trashError"></div>
                <div class="space-y-3">
                    <template x-for="entry in trashItems" :key="entry.id">
                        <div class="flex flex-col gap-3 rounded-xl border border-gray-200 p-4 sm:flex-row sm:items-center">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-semibold text-gray-900" x-text="entry.label"></p>
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600" x-text="entry.type"></span>
                                </div>
                                <p class="mt-1 text-xs text-gray-400">Deleted <span x-text="new Date(entry.deletedAt).toLocaleString()"></span></p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="restoreTrashItem(entry.id)" :disabled="trashSavingId === entry.id" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-100 transition-colors disabled:opacity-60">
                                    <span x-show="trashSavingId === entry.id" class="bms-progress-ring"></span>
                                    <span x-show="trashSavingId !== entry.id">@heroicon('o-arrow-uturn-left', 'w-4 h-4')</span>
                                    <span x-text="trashSavingId === entry.id ? 'Restoring...' : 'Restore'"></span>
                                </button>
                                <button type="button" @click="askDelete('Permanently Delete', entry.label, () => permanentlyDeleteTrashItem(entry.id), { confirmLabel: 'Delete permanently' })" :disabled="trashSavingId === entry.id" class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-white px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50 transition-colors disabled:opacity-60">
                                    <span x-show="trashSavingId === entry.id" class="bms-progress-ring"></span>
                                    <span x-show="trashSavingId !== entry.id">@heroicon('o-trash', 'w-4 h-4')</span>
                                    <span>Delete permanently</span>
                                </button>
                            </div>
                        </div>
                    </template>
                    <div x-show="trashItems.length === 0" class="rounded-xl border border-dashed border-gray-300 py-12 text-center">
                        <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                            @heroicon('o-trash', 'w-5 h-5')
                        </div>
                        <p class="text-sm font-medium text-gray-700">Trash is empty</p>
                        <p class="mt-1 text-xs text-gray-400">Items you delete will appear here.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ========== STAFF ROLES ========== --}}
        <div x-show="activeSection === 'roles' && canManageStaffRoles" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-8 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        @heroicon('o-user-group', 'w-5 h-5')
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Staff Roles</h2>
                        <p class="text-xs text-gray-500">Create the roles that appear when adding or filtering staff</p>
                    </div>
                    <span class="ml-auto text-xs font-medium text-gray-400 bg-gray-100 px-2.5 py-1 rounded-full" x-text="staffRoles.length + ' roles'"></span>
                </div>
            </div>
            <div class="px-8 py-6 space-y-3">
                <template x-for="role in staffRoles" :key="role.id">
                    <div class="flex items-center justify-between p-3.5 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold" x-text="staffRoleInitial(role.label)"></span>
                            <div>
                                <p class="text-sm font-medium text-gray-900" x-text="role.label"></p>
                                <p class="text-[11px] text-gray-400">Role key: <span x-text="role.id"></span></p>
                            </div>
                        </div>
                        <button :disabled="staffRoleSaving" @click="askDelete('Delete Role', role.label, () => removeStaffRole(role))" class="p-1.5 text-gray-400 hover:text-red-500 rounded-lg transition-colors disabled:opacity-50" title="Delete role">@heroicon('o-trash', 'w-4 h-4')</button>
                    </div>
                </template>
                <p x-show="!staffRoles.length" class="text-sm text-gray-400 text-center py-4">No roles yet. Add one below.</p>

                <div x-show="staffRoleError" x-cloak class="flex items-start gap-2 px-3.5 py-2.5 rounded-xl bg-red-50 border border-red-200 text-xs font-medium text-red-700">
                    @heroicon('o-exclamation-circle', 'w-4 h-4 flex-shrink-0 mt-px')
                    <span x-text="staffRoleError"></span>
                </div>

                <div class="inline-flex items-center rounded-xl border border-gray-300 overflow-hidden focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20 transition-all">
                    <input type="text" x-model="newStaffRole" @keydown.enter="addStaffRole()" placeholder="Add role, e.g. Head Chef..." class="h-10 px-4 text-sm outline-none bg-white w-64">
                    <button :disabled="staffRoleSaving" @click="addStaffRole()" class="h-10 px-3 bg-gray-50 text-blue-600 hover:bg-blue-50 transition-colors disabled:opacity-50">@heroicon('o-plus', 'w-4 h-4')</button>
                </div>
                <p class="text-[11px] text-gray-400">Roles are shared with the Staff page. Adding or removing one here updates the role list when adding staff.</p>
            </div>
        </div>

        {{-- ========== MENU & CATEGORIES ========== --}}
        <div x-show="activeSection === 'categories'">
            <div class="flex items-center gap-3 mb-6 px-8 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    @heroicon('o-tag', 'w-5 h-5')
                </div>
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Menu & Categories</h2>
                    <p class="text-xs text-gray-500">Create and manage categories and the units used across Kitchen, recipes and Inventory</p>
                </div>
            </div>
            <div class="space-y-6">
                {{-- Inventory Categories --}}
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-8 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">Inventory Categories</h3>
                            <p class="text-xs text-gray-500">Used to classify products and filter the Inventory list</p>
                        </div>
                        <span class="text-xs font-medium text-gray-400 bg-gray-100 px-2.5 py-1 rounded-full" x-text="inventoryCategories.length + ' categories'"></span>
                    </div>
                    <div class="px-8 py-5">
                        <div class="flex flex-wrap gap-2 mb-4">
                            <template x-for="category in inventoryCategories" :key="category.id">
                                <span class="inline-flex items-center gap-1.5 pl-3 pr-2 py-1.5 bg-blue-50 text-blue-800 rounded-full text-sm">
                                    <span x-text="category.name"></span>
                                    <button @click="askDelete('Remove Inventory Category', category.name, () => removeInventoryCategory(category.id))"
                                            :disabled="inventoryCategoryErrorFor === String(category.id)"
                                            class="text-blue-400 hover:text-red-500 transition-colors" title="Remove category">
                                        <span x-show="inventoryCategoryErrorFor === String(category.id)" class="bms-progress-ring inline-block w-3 h-3"></span>
                                        <span x-show="inventoryCategoryErrorFor !== String(category.id)">@heroicon('o-x-mark', 'w-3.5 h-3.5')</span>
                                    </button>
                                </span>
                            </template>
                        </div>
                        <div x-show="inventoryCategoryError" x-cloak class="mb-3 rounded-xl bg-red-50 border border-red-200 px-3.5 py-2.5 text-xs font-medium text-red-700" x-text="inventoryCategoryError"></div>
                        <div class="inline-flex items-center rounded-xl border border-gray-300 overflow-hidden focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20 transition-all">
                            <input type="text" x-model="inventoryCategoryInput" @keydown.enter="addInventoryCategory()" placeholder="Add inventory category..." class="h-10 px-4 text-sm outline-none bg-white w-64">
                            <button @click="addInventoryCategory()" :disabled="inventoryCategorySaving" class="h-10 px-3 bg-gray-50 text-blue-600 hover:bg-blue-50 transition-colors disabled:opacity-50">
                                <span x-show="inventoryCategorySaving" class="bms-progress-ring inline-block"></span>
                                <span x-show="!inventoryCategorySaving">@heroicon('o-plus', 'w-4 h-4')</span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Raw Material Categories --}}
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-8 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">Raw Material Categories</h3>
                            <p class="text-xs text-gray-500">Used when adding stock items in Kitchen / Inventory</p>
                        </div>
                        <span class="text-xs font-medium text-gray-400 bg-gray-100 px-2.5 py-1 rounded-full" x-text="rawMaterialCategories.length + ' categories'"></span>
                    </div>
                    <div class="px-8 py-5">
                        <div class="flex flex-wrap gap-2 mb-4">
                            <template x-for="cat in rawMaterialCategories" :key="cat.id">
                                <span class="inline-flex items-center gap-1.5 pl-3 pr-2 py-1.5 bg-gray-100 text-gray-700 rounded-full text-sm">
                                    <span x-text="cat.name"></span>
                                    <button @click="askDelete('Remove Category', cat.name, () => removeRawMatCat(cat.id))" :disabled="rawMaterialCategorySaving" class="text-gray-400 hover:text-red-500 transition-colors"><span x-show="rawMaterialCategorySaving" class="bms-progress-ring inline-block w-3 h-3"></span><span x-show="!rawMaterialCategorySaving">@heroicon('o-x-mark', 'w-3.5 h-3.5')</span></button>
                                </span>
                            </template>
                        </div>
                        <div x-show="rawMaterialCategoryError" x-cloak class="mb-3 rounded-xl bg-red-50 border border-red-200 px-3.5 py-2.5 text-xs font-medium text-red-700" x-text="rawMaterialCategoryError"></div>
                        <div class="inline-flex items-center rounded-xl border border-gray-300 overflow-hidden focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20 transition-all">
                            <input type="text" x-model="newRawMatCat" @keydown.enter="addRawMatCat()" placeholder="Add raw material category..." class="h-10 px-4 text-sm outline-none bg-white w-64">
                            <button @click="addRawMatCat()" :disabled="rawMaterialCategorySaving" class="h-10 px-3 bg-gray-50 text-blue-600 hover:bg-blue-50 transition-colors disabled:opacity-50"><span x-show="rawMaterialCategorySaving" class="bms-progress-ring inline-block"></span><span x-show="!rawMaterialCategorySaving">@heroicon('o-plus', 'w-4 h-4')</span></button>
                        </div>
                    </div>
                </div>

                {{-- Food Item Categories --}}
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-8 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">Food Item Categories</h3>
                            <p class="text-xs text-gray-500">Used on the POS menu and Kitchen food items</p>
                        </div>
                        <span class="text-xs font-medium text-gray-400 bg-gray-100 px-2.5 py-1 rounded-full" x-text="foodItemCategories.length + ' categories'"></span>
                    </div>
                    <div class="px-8 py-5">
                        <div class="space-y-2 mb-4">
                            <template x-for="(cat, i) in foodItemCategories" :key="i">
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <span class="w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold" :class="['bg-blue-100 text-blue-700', 'bg-amber-100 text-amber-700', 'bg-emerald-100 text-emerald-700', 'bg-purple-100 text-purple-700', 'bg-pink-100 text-pink-700'][i % 5]">
                                            <span x-text="cat.charAt(0).toUpperCase()"></span>
                                        </span>
                                        <span class="text-sm font-medium text-gray-900" x-text="cat"></span>
                                    </div>
                                    <button @click="askDelete('Remove Category', cat, () => removeFoodCat(i))" class="p-1.5 text-gray-400 hover:text-red-500 rounded-lg transition-colors">@heroicon('o-x-mark', 'w-4 h-4')</button>
                                </div>
                            </template>
                        </div>
                        <div class="inline-flex items-center rounded-xl border border-gray-300 overflow-hidden focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20 transition-all">
                            <input type="text" x-model="newFoodCat" @keydown.enter="addFoodCat()" placeholder="Add food item category..." class="h-10 px-4 text-sm outline-none bg-white w-64">
                            <button @click="addFoodCat()" class="h-10 px-3 bg-gray-50 text-blue-600 hover:bg-blue-50 transition-colors">@heroicon('o-plus', 'w-4 h-4')</button>
                        </div>
                    </div>
                </div>

                {{-- Recipe Categories --}}
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-8 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">Recipe Categories</h3>
                            <p class="text-xs text-gray-500">Organise recipes by cooking style</p>
                        </div>
                        <span class="text-xs font-medium text-gray-400 bg-gray-100 px-2.5 py-1 rounded-full" x-text="recipeCategories.length + ' categories'"></span>
                    </div>
                    <div class="px-8 py-5">
                        <div class="flex flex-wrap gap-2 mb-4">
                            <template x-for="(cat, i) in recipeCategories" :key="i">
                                <span class="inline-flex items-center gap-1.5 pl-3 pr-2 py-1.5 bg-gray-100 text-gray-700 rounded-full text-sm">
                                    <span x-text="cat"></span>
                                    <button @click="askDelete('Remove Category', cat, () => removeRecipeCat(i))" class="text-gray-400 hover:text-red-500 transition-colors">@heroicon('o-x-mark', 'w-3.5 h-3.5')</button>
                                </span>
                            </template>
                        </div>
                        <div class="inline-flex items-center rounded-xl border border-gray-300 overflow-hidden focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20 transition-all">
                            <input type="text" x-model="newRecipeCat" @keydown.enter="addRecipeCat()" placeholder="Add recipe category..." class="h-10 px-4 text-sm outline-none bg-white w-64">
                            <button @click="addRecipeCat()" class="h-10 px-3 bg-gray-50 text-blue-600 hover:bg-blue-50 transition-colors">@heroicon('o-plus', 'w-4 h-4')</button>
                        </div>
                    </div>
                </div>

                {{-- Units --}}
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-8 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">Units</h3>
                            <p class="text-xs text-gray-500">Used in Kitchen raw materials, recipe output and Inventory products</p>
                        </div>
                        <span class="text-xs font-medium text-gray-400 bg-gray-100 px-2.5 py-1 rounded-full" x-text="units.length + ' units'"></span>
                    </div>
                    <div class="px-8 py-5">
                        <div class="flex flex-wrap gap-2 mb-4">
                            <template x-for="(unit, i) in units" :key="unit + '-' + i">
                                <span class="inline-flex items-center gap-1.5 pl-3 pr-2 py-1.5 bg-gray-100 text-gray-700 rounded-full text-sm">
                                    <span x-text="unit"></span>
                                    <button @click="askDelete('Remove Unit', unit, () => removeUnit(i))" :disabled="unitSaving" class="text-gray-400 hover:text-red-500 transition-colors disabled:opacity-50" title="Remove unit">
                                        <span x-show="unitSaving" class="bms-progress-ring inline-block w-3 h-3"></span>
                                        <span x-show="!unitSaving">@heroicon('o-x-mark', 'w-3.5 h-3.5')</span>
                                    </button>
                                </span>
                            </template>
                            <p x-show="!units.length" class="text-sm text-gray-400 py-1.5">No units yet. Add your first one below.</p>
                        </div>
                        <div class="inline-flex items-center rounded-xl border border-gray-300 overflow-hidden focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20 transition-all">
                            <input type="text" x-model="newUnit" @keydown.enter="addUnit()" placeholder="Add unit, e.g. sachet..." class="h-10 px-4 text-sm outline-none bg-white w-64">
                            <button @click="addUnit()" :disabled="unitSaving" class="h-10 px-3 bg-gray-50 text-blue-600 hover:bg-blue-50 transition-colors disabled:opacity-50" title="Add unit">
                                <span x-show="unitSaving" class="bms-progress-ring inline-block"></span>
                                <span x-show="!unitSaving">@heroicon('o-plus', 'w-4 h-4')</span>
                            </button>
                        </div>
                        <p x-show="unitError" x-cloak class="mt-2 text-xs font-medium text-red-600" x-text="unitError"></p>
                        <p class="text-[11px] text-gray-400 mt-2">Custom units appear in every unit dropdown after you open Kitchen or Inventory.</p>
                    </div>
                </div>

                {{-- Expense Categories --}}
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-8 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">Expense Categories</h3>
                            <p class="text-xs text-gray-500">Used for tracking expenses and P&L</p>
                        </div>
                        <span class="text-xs font-medium text-gray-400 bg-gray-100 px-2.5 py-1 rounded-full" x-text="expenseCategories.length + ' categories'"></span>
                    </div>
                    <div class="px-8 py-5">
                        <div class="flex flex-wrap gap-2 mb-4">
                            <template x-for="cat in expenseCategories" :key="cat.recordId">
                                <span class="inline-flex items-center gap-1.5 pl-3 pr-2 py-1.5 bg-gray-100 text-gray-700 rounded-full text-sm">
                                    <span x-text="cat.label"></span>
                                    <button @click="askDelete('Remove Category', cat.label, () => removeExpenseCategory(cat))" :disabled="expenseCategorySaving" class="text-gray-400 hover:text-red-500 transition-colors disabled:opacity-50">@heroicon('o-x-mark', 'w-3.5 h-3.5')</button>
                                </span>
                            </template>
                        </div>
                        <div class="inline-flex items-center rounded-xl border border-gray-300 overflow-hidden focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20 transition-all">
                            <input type="text" x-model="newExpenseCat" @keydown.enter="addExpenseCategory()" placeholder="Add expense category..." class="h-10 px-4 text-sm outline-none bg-white w-64">
                            <button @click="addExpenseCategory()" :disabled="expenseCategorySaving" class="h-10 px-3 bg-gray-50 text-blue-600 hover:bg-blue-50 transition-colors disabled:opacity-50">@heroicon('o-plus', 'w-4 h-4')</button>
                        </div>
                        <p x-show="expenseCategoryError" x-cloak class="mt-2 text-xs font-medium text-red-600" x-text="expenseCategoryError"></p>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- ========== CONFIRMATION DIALOG ========== --}}
    <template x-teleport="body">
        <div x-show="confirmState.open"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             @click.away="closeConfirm()"
             @keydown.escape.window="closeConfirm()"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="closeConfirm()"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm z-10"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
                <div class="px-6 py-5 text-center">
                    <div class="inline-flex items-center justify-center w-14 h-14 rounded-full mb-4"
                         :class="confirmState.variant === 'warning' ? 'bg-amber-100 text-amber-600' : 'bg-red-100 text-red-600'">
                        @heroicon('o-exclamation-triangle', 'w-7 h-7')
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2" x-text="confirmState.title"></h3>
                    <p class="text-sm text-gray-500 mb-1" x-text="confirmState.message"></p>
                    <p class="text-base font-bold text-gray-900 break-words" x-text="confirmState.item" x-show="confirmState.item"></p>
                    <p class="text-xs text-gray-400 mt-1">This action cannot be undone.</p>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex gap-2">
                    <button @click="closeConfirm()" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white">Cancel</button>
                    <button @click="runConfirm()"
                            class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-white shadow-sm"
                            :class="confirmState.variant === 'warning' ? 'bg-amber-600 hover:bg-amber-700 shadow-amber-200' : 'bg-red-600 hover:bg-red-700 shadow-red-200'"
                            x-text="confirmState.confirmLabel"></button>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection
