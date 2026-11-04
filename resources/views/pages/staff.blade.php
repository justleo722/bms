@extends('layouts.master', ['activeNav' => $activeNav ?? 'staff'])

@section('title', 'Staff')

@push('scripts')
<script>
window.staffData = function (initialQuery) {
    return {
        activeTab: 'list',
        activeRole: 'all',
        searchQuery: initialQuery || '',
        showAddModal: false,
        showPermissionsModal: false,
        showDeleteModal: false,
        showAdminAuthModal: false,
        staffToDelete: null,
        selectedStaff: null,
        editingId: null,
        adminAuthPassword: '',
        adminAuthError: '',
        adminAuthIntent: '',
        adminAuthTitle: '',
        adminAuthMessage: '',
        pendingAdminAction: null,
        verifiedAdminPassword: '',
        permissionsUnlocked: false,
        permissionsAdminPassword: '',
        revealedCodeIds: [],
        saving: false,
        staffError: '',
        permissionSaveTimer: null,
        liveTimer: null,
        createdSignInCode: '',
        isAdministrator: @json(auth()->user()->isAdministrator()),
        canAddStaff: @json(auth()->user()->hasPermission('staff', 'add')),

        modules: [
            { key: 'dashboard', name: 'Dashboard', components: [
                { key: 'todaySales', label: "View today's sales" },
                { key: 'todayProfit', label: "View today's profit" },
                { key: 'todayExpenses', label: "View today's expenses" },
                { key: 'quickActions', label: 'Access quick actions' }
            ] },
            { key: 'pos', name: 'POS', components: [
                { key: 'sell', label: 'Can sell' },
                { key: 'printDocket', label: 'Can print docket' }
            ] },
            { key: 'sales', name: 'Sales', components: [
                { key: 'view', label: "View today's sales" },
                { key: 'viewOthers', label: "View other attendants' sales" },
                { key: 'profit', label: "View today's profit" },
                { key: 'edit', label: 'Edit sale' },
                { key: 'backdate', label: 'Backdate sale' },
                { key: 'delete', label: 'Delete sale' },
                { key: 'reprint', label: 'Reprint docket' },
                { key: 'printBill', label: 'Print bill' },
                { key: 'export', label: 'Export sales' }
            ] },
            { key: 'kitchen', name: 'Kitchen', components: [
                { key: 'rawAdd', label: 'Add / restock raw materials' },
                { key: 'rawEdit', label: 'Edit / adjust raw materials' },
                { key: 'rawDelete', label: 'Delete raw materials' },
                { key: 'rawValue', label: 'View raw material stock value' },
                { key: 'recipeAdd', label: 'Add new recipe' },
                { key: 'recipeEdit', label: 'Edit recipe' },
                { key: 'recipeDelete', label: 'Delete recipe' },
                { key: 'foodAdd', label: 'Add food items' },
                { key: 'foodEdit', label: 'Edit food items' },
                { key: 'foodDelete', label: 'Delete food items' }
            ] },
            { key: 'inventory', name: 'Inventory', components: [
                { key: 'addRestock', label: 'Add / restock / import products' },
                { key: 'editAdjust', label: 'Edit / adjust products' },
                { key: 'transfer', label: 'Transfer products' },
                { key: 'delete', label: 'Delete products' },
                { key: 'value', label: 'View inventory value & profit estimate' }
            ] },
            { key: 'expenses', name: 'Profit & Expenses', components: [
                { key: 'add', label: 'Add expenses' },
                { key: 'delete', label: 'Delete expenses' },
                { key: 'view', label: 'View profit breakdown' }
            ] },
            { key: 'reports', name: 'Reports', components: [
                { key: 'viewExport', label: 'View and export reports' }
            ] },
            { key: 'staff', name: 'Staff', components: [
                { key: 'add', label: 'Add other attendants' }
            ] },
            { key: 'rooms', name: 'Rooms', components: [
                { key: 'view', label: 'View room availability and stays' },
                { key: 'checkIn', label: 'Check guests in' },
                { key: 'checkOut', label: 'Check guests out' },
                { key: 'reservations', label: 'Manage reservations' },
                { key: 'housekeeping', label: 'Update room housekeeping status' },
                { key: 'manage', label: 'Add, edit and delete rooms' },
                { key: 'finance', label: 'Record room expenses and view room profit' }
            ] },
            { key: 'settings', name: 'Settings', components: [
                { key: 'manage', label: 'Manage settings' }
            ] }
        ],
        allRoles: @json($staffRoles),
        counters: @json($staffCounters),
        staff: @json($staffMembers),
        form: { name: '', phone: '', role: '', counter_id: '', status: 'active' },

        init() {
            var self = this;
            this.staff.forEach(function (member) {
                member.permissions = self.normalizePermissions(member.permissions);
            });
            this.startLiveRefresh();
        },

        startLiveRefresh() {
            var self = this;
            this.liveTimer = setInterval(function () { self.refreshStaff(); }, 15000);
        },

        refreshStaff() {
            if (document.hidden || this.saving || this.showAddModal || this.showPermissionsModal || this.showAdminAuthModal || this.showDeleteModal) {
                return;
            }

            var self = this;
            window.axios.get('/staff/data', { headers: { 'Accept': 'application/json' } })
                .then(function (response) {
                    var data = response.data || {};
                    var incoming = data.staff || [];
                    incoming.forEach(function (member) {
                        member.permissions = self.normalizePermissions(member.permissions);
                        var existing = self.staff.find(function (item) { return item.id === member.id; });
                        if (existing && self.revealedCodeIds.indexOf(member.id) !== -1) {
                            member.signInCode = existing.signInCode;
                        }
                    });
                    var changed = JSON.stringify(incoming) !== JSON.stringify(self.staff)
                        || JSON.stringify(data.roles || []) !== JSON.stringify(self.allRoles)
                        || JSON.stringify(data.counters || []) !== JSON.stringify(self.counters);
                    if (!changed) { return; }

                    self.staff = incoming;
                    self.allRoles = data.roles || [];
                    self.counters = data.counters || [];
                })
                .catch(function () {
                    // A failed background refresh should never interrupt the page.
                });
        },

        emptyPermissions() {
            var permissions = {};
            this.modules.forEach(function (mod) {
                var components = {};
                mod.components.forEach(function (comp) { components[comp.key] = false; });
                permissions[mod.key] = { enabled: false, components: components };
            });
            return permissions;
        },

        normalizePermissions(source) {
            var permissions = this.emptyPermissions();
            if (!source) { return permissions; }

            this.modules.forEach(function (mod) {
                var incoming = source[mod.key];
                if (!incoming) { return; }

                permissions[mod.key].enabled = !!incoming.enabled;
                mod.components.forEach(function (comp) {
                    if (incoming.components && incoming.components[comp.key] !== undefined) {
                        permissions[mod.key].components[comp.key] = !!incoming.components[comp.key];
                    }
                });
            });

            return permissions;
        },

        csrfHeaders() {
            return { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content };
        },

        apiError(error, fallback) {
            return error && error.response && error.response.data && error.response.data.message
                ? error.response.data.message : fallback;
        },

        persistStaff(member) {
            if (!member) { return; }
            clearTimeout(this.permissionSaveTimer);
            var self = this;
            this.permissionSaveTimer = setTimeout(function () {
                window.axios.put('/staff/' + member.id + '/permissions', {
                    permissions: member.permissions,
                    admin_password: self.permissionsAdminPassword || null,
                }, { headers: self.csrfHeaders() })
                    .catch(function (error) {
                        self.staffError = self.apiError(error, 'Could not save staff permissions.');
                    });
            }, 200);
        },

        get filteredStaff() {
            var r = this.activeRole;
            var q = this.searchQuery.toLowerCase().trim();
            return this.staff.filter(function (s) {
                if (r !== 'all' && s.role !== r) { return false; }
                if (!q) { return true; }
                var haystack = [s.name, s.phone, s.role, s.counter, s.status]
                    .map(function (v) { return String(v || '').toLowerCase(); })
                    .join(' ');
                return haystack.includes(q);
            });
        },

        get roles() {
            return [{ id: 'all', label: 'All Staff' }].concat(this.allRoles);
        },

        get activeCount() { return this.staff.filter(function (s) { return s.status === 'active'; }).length; },
        get inactiveCount() { return this.staff.filter(function (s) { return s.status !== 'active'; }).length; },

        roleBadge(role) {
            var m = { cashier: 'bg-blue-50 text-blue-700', bartender: 'bg-purple-50 text-purple-700', manager: 'bg-amber-50 text-amber-700', waiter: 'bg-emerald-50 text-emerald-700', kitchen: 'bg-orange-50 text-orange-700', security: 'bg-gray-100 text-gray-600' };
            return m[role] || 'bg-gray-100 text-gray-600';
        },

        roleLabel(role) {
            var found = this.allRoles.filter(function (r) { return r.id === role; })[0];
            if (found) { return found.label; }
            return (role || '').charAt(0).toUpperCase() + (role || '').slice(1);
        },

        initials(name) {
            var parts = (name || '').trim().split(/\s+/).filter(Boolean);
            if (!parts.length) { return '?'; }
            if (parts.length === 1) { return parts[0].slice(0, 2).toUpperCase(); }
            return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
        },

        nextColor() {
            var palette = [
                'bg-gradient-to-br from-blue-500 to-cyan-500',
                'bg-gradient-to-br from-emerald-500 to-teal-500',
                'bg-gradient-to-br from-purple-500 to-pink-500',
                'bg-gradient-to-br from-amber-500 to-orange-500',
                'bg-gradient-to-br from-indigo-500 to-blue-500',
                'bg-gradient-to-br from-rose-500 to-red-500',
                'bg-gradient-to-br from-violet-500 to-purple-500',
                'bg-gradient-to-br from-gray-500 to-slate-600',
            ];
            return palette[this.staff.length % palette.length];
        },

        isCodeVisible(member) {
            return !!member && this.revealedCodeIds.indexOf(member.id) !== -1;
        },

        toggleCodeVisibility(member) {
            if (!member) { return; }

            if (this.isCodeVisible(member)) {
                this.revealedCodeIds = this.revealedCodeIds.filter(function (id) { return id !== member.id; });
                return;
            }

            var self = this;
            this.requestAdminPassword('code', function () {
                window.axios.get('/staff/' + member.id + '/pin')
                    .then(function (response) {
                        member.signInCode = response.data.pin;
                        if (self.revealedCodeIds.indexOf(member.id) === -1) {
                            self.revealedCodeIds = self.revealedCodeIds.concat(member.id);
                        }
                    })
                    .catch(function (error) { self.staffError = self.apiError(error, 'Could not retrieve the sign-in code.'); });
            }, member);
        },

        regenerateSignInCode(member) {
            if (!member) { return; }

            var self = this;
            this.requestAdminPassword('regenerate', function () {
                window.axios.post('/staff/' + member.id + '/pin', {}, { headers: self.csrfHeaders() })
                    .then(function (response) {
                        member.signInCode = response.data.pin;
                        self.revealedCodeIds = self.revealedCodeIds.filter(function (id) { return id !== member.id; });
                        self.revealedCodeIds = self.revealedCodeIds.concat(member.id);
                    })
                    .catch(function (error) { self.staffError = self.apiError(error, 'Could not regenerate the sign-in code.'); });
            }, member);
        },

        requestAdminPassword(intent, action, member) {
            var who = member && member.name ? member.name : 'this staff member';
            this.adminAuthIntent = intent;
            this.adminAuthTitle = intent === 'code'
                ? 'View sign-in code'
                : (intent === 'regenerate'
                    ? 'Regenerate sign-in code'
                    : (intent === 'edit'
                        ? 'Edit staff member'
                        : (intent === 'delete' ? 'Remove staff member' : 'Confirm administrator password')));
            this.adminAuthMessage = intent === 'code'
                ? 'Enter the administrator password to reveal ' + who + '\'s sign-in code.'
                : (intent === 'regenerate'
                    ? 'Enter the administrator password to generate a new sign-in code for ' + who + '. The current code will stop working.'
                    : (intent === 'edit'
                        ? 'Enter the administrator password to edit ' + who + '\'s details.'
                        : (intent === 'delete'
                            ? 'Enter the administrator password to remove ' + who + ' from staff.'
                            : 'Enter the administrator password to manage ' + who + '\'s permissions.')));
            this.adminAuthPassword = '';
            this.adminAuthError = '';
            this.pendingAdminAction = action || null;
            this.showAdminAuthModal = true;

            var self = this;
            this.$nextTick(function () {
                if (self.$refs.adminAuthInput) { self.$refs.adminAuthInput.focus(); }
            });
        },

        closeAdminAuth() {
            this.showAdminAuthModal = false;
            this.adminAuthPassword = '';
            this.adminAuthError = '';
            this.adminAuthIntent = '';
            this.pendingAdminAction = null;
        },

        confirmAdminAuth() {
            if (!String(this.adminAuthPassword || '').trim()) {
                this.adminAuthError = 'Enter the administrator password.';
                return;
            }

            var self = this;
            var adminPassword = this.adminAuthPassword;
            window.axios.post('/staff/authorize', { password: this.adminAuthPassword }, { headers: this.csrfHeaders() })
                .then(function () {
                    var action = self.pendingAdminAction;
                    var intent = self.adminAuthIntent;
                    self.verifiedAdminPassword = adminPassword;
                    self.closeAdminAuth();
                    if (intent === 'permissions') {
                        self.permissionsUnlocked = true;
                        self.permissionsAdminPassword = adminPassword;
                    }
                    if (typeof action === 'function') action();
                })
                .catch(function (error) {
                    self.adminAuthError = self.apiError(error, 'Administrator verification failed.');
                });
        },

        toggleModuleFromMatrix(member, mod) {
            if (!member) { return; }
            if (this.permissionsUnlocked) {
                this.toggleModule(member, mod);
                return;
            }

            var self = this;
            this.requestAdminPassword('permissions', function () {
                self.toggleModule(member, mod);
            }, member);
        },

        openAddStaff() {
            this.editingId = null;
            this.staffError = '';
            this.form = {
                name: '',
                phone: '',
                role: (this.allRoles[0] || {}).id || '',
                counter_id: this.counters.length ? this.counters[0].id : '',
                status: 'active',
            };
            this.showAddModal = true;
        },

        openEditStaff(member) {
            if (!member) { return; }
            var self = this;
            this.requestAdminPassword('edit', function () {
                self.showEditForm(member);
            }, member);
        },

        showEditForm(member) {
            this.editingId = member.id;
            this.form = {
                name: member.name,
                phone: member.phone,
                role: member.role,
                counter_id: member.counterId || '',
                status: member.status,
            };
            this.staffError = '';
            this.showAddModal = true;
        },

        saveStaff() {
            var name = (this.form.name || '').trim();
            if (!name) { return; }
            var self = this;
            if (this.saving) return;
            this.saving = true;
            this.staffError = '';
            var payload = {
                name: name,
                phone: this.form.phone,
                role: this.form.role,
                counter_id: this.form.counter_id || null,
                status: this.form.status,
            };
            if (this.editingId) {
                payload.admin_password = this.verifiedAdminPassword || null;
            }
            var request = this.editingId
                ? window.axios.put('/staff/' + this.editingId, payload, { headers: this.csrfHeaders() })
                : window.axios.post('/staff', payload, { headers: this.csrfHeaders() });
            request.then(function (response) {
                var member = response.data.staff;
                member.permissions = self.normalizePermissions(member.permissions);
                if (self.editingId) {
                    var index = self.staff.findIndex(function (item) { return item.id === member.id; });
                    if (index >= 0) self.staff.splice(index, 1, member);
                } else {
                    self.staff.push(member);
                    self.revealedCodeIds = self.revealedCodeIds.concat(member.id);
                    self.createdSignInCode = member.signInCode || '';
                }
                self.verifiedAdminPassword = '';
                self.showAddModal = false;
            }).catch(function (error) {
                self.staffError = self.apiError(error, 'Could not save this staff member.');
            }).then(function () {
                self.saving = false;
            });
        },

        askDeleteStaff(member) {
            if (!member) { return; }
            var self = this;
            this.requestAdminPassword('delete', function () {
                self.staffToDelete = member;
                self.showDeleteModal = true;
            }, member);
        },

        closeDeleteStaff() {
            this.showDeleteModal = false;
            this.staffToDelete = null;
        },

        confirmDeleteStaff() {
            if (!this.staffToDelete) { return; }
            var self = this;
            var id = this.staffToDelete.id;
            window.axios.delete('/staff/' + id, {
                headers: self.csrfHeaders(),
                data: { admin_password: this.verifiedAdminPassword || null },
            })
                .then(function () {
                    self.staff = self.staff.filter(function (member) { return member.id !== id; });
                    if (self.selectedStaff && self.selectedStaff.id === id) {
                        self.selectedStaff = null;
                        self.showPermissionsModal = false;
                    }
                    self.revealedCodeIds = self.revealedCodeIds.filter(function (codeId) { return codeId !== id; });
                    self.verifiedAdminPassword = '';
                    self.closeDeleteStaff();
                })
                .catch(function (error) { self.staffError = self.apiError(error, 'Could not remove this staff member.'); });
        },

        openPermissions(member) {
            if (!member) { return; }
            this.permissionsUnlocked = false;
            this.permissionsAdminPassword = '';
            var self = this;
            this.requestAdminPassword('permissions', function () {
                self.selectedStaff = member;
                self.showPermissionsModal = true;
            }, member);
        },

        permission(moduleKey) {
            return (this.selectedStaff && this.selectedStaff.permissions[moduleKey]) || { enabled: false, components: {} };
        },

        isModuleEnabled(member, key) {
            return !!(member && member.permissions[key] && member.permissions[key].enabled);
        },

        enabledModuleCount(member) {
            if (!member) { return 0; }
            var self = this;
            return this.modules.filter(function (m) { return self.isModuleEnabled(member, m.key); }).length;
        },

        permissionsSummary(member) {
            if (!member) { return ''; }
            var modules = this.enabledModuleCount(member);
            if (!modules) { return 'No module access'; }
            var total = 0;
            var granted = 0;
            this.modules.forEach(function (m) {
                if (!member.permissions[m.key] || !member.permissions[m.key].enabled) { return; }
                total += m.components.length;
                granted += m.components.filter(function (c) {
                    return member.permissions[m.key].components[c.key];
                }).length;
            });
            return modules + ' of ' + this.modules.length + ' modules · ' + granted + '/' + total + ' permissions';
        },

        moduleComponentCount(member, mod) {
            var perms = member && member.permissions[mod.key];
            if (!perms || !perms.enabled) { return mod.components.length + ' permissions'; }
            var on = mod.components.filter(function (c) { return perms.components && perms.components[c.key]; }).length;
            return on + ' of ' + mod.components.length + ' enabled';
        },

        componentEnabled(member, moduleKey, compKey) {
            var perms = member && member.permissions[moduleKey];
            return !!(perms && perms.components && perms.components[compKey]);
        },

        toggleModule(member, mod) {
            if (!member) { return; }
            if (!member.permissions[mod.key]) {
                member.permissions[mod.key] = { enabled: false, components: {} };
            }
            var perms = member.permissions[mod.key];
            if (!perms.components) { perms.components = {}; }
            perms.enabled = !perms.enabled;
            if (perms.enabled) {
                mod.components.forEach(function (c) { perms.components[c.key] = true; });
            }
            this.persistStaff(member);
        },

        toggleComponent(member, moduleKey, compKey) {
            if (!member) { return; }
            if (!member.permissions[moduleKey]) {
                member.permissions[moduleKey] = { enabled: true, components: {} };
            }
            var perms = member.permissions[moduleKey];
            if (!perms.components) { perms.components = {}; }
            perms.components[compKey] = !perms.components[compKey];
            if (perms.components[compKey]) {
                perms.enabled = true;
            } else if (!Object.keys(perms.components).some(function (key) { return perms.components[key]; })) {
                perms.enabled = false;
            }
            this.persistStaff(member);
        },
    };
};
</script>
@endpush

@section('content')
<div x-data="staffData(@js(is_string(request('q')) ? request('q') : ''))" class="pb-6">
    
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Staff</h1>
            <p class="mt-1 text-sm text-gray-500">Manage staff, roles, and system permissions.</p>
        </div>
        <div class="flex items-center gap-3 mt-4 sm:mt-0">
            <span class="hidden sm:inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-medium text-emerald-700" title="Updates automatically every 15 seconds">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                Live
            </span>
            <x-button variant="primary" size="sm" icon="o-plus" @click="openAddStaff()">Add Staff</x-button>
        </div>
    </div>
    <div x-show="staffError" x-cloak class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" x-text="staffError"></div>
    <div x-show="createdSignInCode" x-cloak class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        Staff member created. Share this sign-in code securely:
        <strong class="ml-2 font-mono tracking-widest" x-text="createdSignInCode"></strong>
        <button type="button" class="ml-3 text-emerald-700 underline" @click="createdSignInCode = ''">Hide</button>
    </div>

    
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-500 truncate">Total Staff</p>
                    <p class="mt-1.5 text-2xl font-bold text-gray-900 tracking-tight" x-text="staff.length"></p>
                    <span class="text-sm text-gray-400">members</span>
                </div>
                <div class="flex-shrink-0 w-12 h-12 rounded-xl flex items-center justify-center bg-blue-50 text-blue-600">
                    @heroicon('o-users', 'w-6 h-6')
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-500 truncate">Active</p>
                    <p class="mt-1.5 text-2xl font-bold text-gray-900 tracking-tight" x-text="activeCount"></p>
                    <span class="text-sm text-gray-400">currently active</span>
                </div>
                <div class="flex-shrink-0 w-12 h-12 rounded-xl flex items-center justify-center bg-emerald-50 text-emerald-600">
                    @heroicon('o-check-circle', 'w-6 h-6')
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-500 truncate">Inactive</p>
                    <p class="mt-1.5 text-2xl font-bold text-gray-900 tracking-tight" x-text="inactiveCount"></p>
                    <span class="text-sm text-gray-400">off duty</span>
                </div>
                <div class="flex-shrink-0 w-12 h-12 rounded-xl flex items-center justify-center bg-gray-100 text-gray-600">
                    @heroicon('o-x-circle', 'w-6 h-6')
                </div>
            </div>
        </div>
    </div>

    
    <div class="flex items-center gap-1 mb-6 border-b border-gray-200">
        <button @click="activeTab = 'list'" :class="activeTab === 'list' ? 'border-b-2 border-blue-600 text-blue-600 font-semibold' : 'text-gray-500 hover:text-gray-700 border-b-2 border-transparent'" class="px-4 py-3 text-sm font-medium transition-all -mb-px">
            Staff List
        </button>
        <button @click="activeTab = 'permissions'" :class="activeTab === 'permissions' ? 'border-b-2 border-blue-600 text-blue-600 font-semibold' : 'text-gray-500 hover:text-gray-700 border-b-2 border-transparent'" class="px-4 py-3 text-sm font-medium transition-all -mb-px">
            Permissions
        </button>
    </div>

    
    <div x-show="activeTab === 'list'">
        
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
            <div class="flex items-center gap-2 overflow-x-auto no-scrollbar flex-1">
                <template x-for="role in roles" :key="role.id">
                    <button @click="activeRole = role.id"
                            :class="activeRole === role.id ? 'bg-blue-600 text-white shadow-sm shadow-blue-200' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'"
                            class="flex-shrink-0 px-3 py-1.5 rounded-lg text-xs font-medium transition-all"
                            x-text="role.label"></button>
                </template>
            </div>

            {{-- Search staff --}}
            <div class="relative w-full sm:w-64 flex-shrink-0">
                @heroicon('o-magnifying-glass', 'absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none')
                <input type="search" x-model.debounce.300ms="searchQuery" placeholder="Search staff by name, role..."
                       class="w-full h-9 pl-9 pr-8 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all placeholder:text-gray-400">
                <button @click="searchQuery = ''" x-show="searchQuery" x-cloak
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                    @heroicon('o-x-circle', 'w-4 h-4')
                </button>
            </div>
        </div>

        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <template x-for="member in filteredStaff" :key="member.id">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 hover:shadow-md transition-all duration-200">
                    <div class="flex items-start gap-3">
                        <div class="w-11 h-11 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0" :class="member.color">
                            <span x-text="member.initials"></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-semibold text-gray-900 truncate" x-text="member.name"></h3>
                                <span class="w-2 h-2 rounded-full flex-shrink-0" :class="member.status === 'active' ? 'bg-emerald-500' : 'bg-gray-300'"></span>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5" x-text="member.phone"></p>
                        </div>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-2" x-text="permissionsSummary(member)"></p>
                    <div x-show="isAdministrator" class="mt-3 flex items-center justify-between rounded-lg border border-gray-100 bg-gray-50/80 px-3 py-2">
                        <div class="min-w-0">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Sign-in code</p>
                            <p class="mt-0.5 font-mono text-sm font-semibold tracking-[0.25em] text-gray-800"
                               x-text="isCodeVisible(member) ? member.signInCode : '••••'"></p>
                        </div>
                        <div class="flex items-center gap-1">
                            <button type="button"
                                    @click="toggleCodeVisibility(member)"
                                    :title="isCodeVisible(member) ? 'Hide sign-in code' : 'Reveal sign-in code'"
                                    :aria-label="isCodeVisible(member) ? 'Hide sign-in code' : 'Reveal sign-in code'"
                                    class="p-1.5 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors">
                                <span x-show="!isCodeVisible(member)">@heroicon('o-eye', 'w-4 h-4')</span>
                                <span x-show="isCodeVisible(member)" x-cloak>@heroicon('o-eye-slash', 'w-4 h-4')</span>
                            </button>
                            <button type="button"
                                    @click="regenerateSignInCode(member)"
                                    title="Regenerate sign-in code"
                                    aria-label="Regenerate sign-in code"
                                    class="p-1.5 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50 transition-colors">
                                @heroicon('o-arrow-path', 'w-4 h-4')
                            </button>
                        </div>
                    </div>
                    <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium" :class="roleBadge(member.role)" x-text="roleLabel(member.role)"></span>
                            <span class="text-[10px] text-gray-400 truncate" x-text="member.counter"></span>
                        </div>
                        <div class="flex items-center gap-0.5 flex-shrink-0">
                            <button x-show="isAdministrator || canAddStaff" @click="openPermissions(member)" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-medium text-blue-600 hover:bg-blue-50 transition-colors" title="Permissions">
                                @heroicon('o-key', 'w-3.5 h-3.5')
                                <span>Permissions</span>
                            </button>
                            <button @click="openEditStaff(member)" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors" title="Edit">
                                @heroicon('o-pencil-square', 'w-4 h-4')
                            </button>
                            <button @click="askDeleteStaff(member)" class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors" title="Remove">
                                @heroicon('o-trash', 'w-4 h-4')
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <template x-if="filteredStaff.length === 0">
            <div class="text-center py-12 text-sm text-gray-400">
                <span x-text="searchQuery.trim() ? 'No staff match your search.' : 'No staff in this role yet.'"></span>
            </div>
        </template>
    </div>

    
    <div x-show="activeTab === 'permissions'">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="scroll-x overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/50">
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Staff Member</th>
                            <template x-for="mod in modules" :key="mod.key">
                                <th class="text-center px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider" x-text="mod.name"></th>
                            </template>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template x-for="member in staff" :key="member.id">
                            <tr class="hover:bg-gray-50/40 transition-colors">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-[10px] font-bold" :class="member.color">
                                            <span x-text="member.initials"></span>
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900" x-text="member.name"></p>
                                            <p class="text-[10px] text-gray-400" x-text="roleLabel(member.role)"></p>
                                        </div>
                                    </div>
                                </td>
                                <template x-for="mod in modules" :key="mod.key">
                                    <td class="px-3 py-3 text-center">
                                        <button @click="toggleModuleFromMatrix(member, mod)"
                                                :class="isModuleEnabled(member, mod.key) ? 'bg-emerald-500' : 'bg-gray-200'"
                                                class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                                            <span :class="isModuleEnabled(member, mod.key) ? 'translate-x-4' : 'translate-x-0.5'"
                                                  class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition-transform duration-200"></span>
                                        </button>
                                    </td>
                                </template>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-3">Changing permissions requires the administrator password. Toggle a module on, then use the Permissions button on a staff card to choose exactly which parts of that module they can use.</p>
    </div>

    
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
                <div class="flex flex-shrink-0 items-center justify-between border-b border-gray-200 px-5 py-4 sm:px-6">
                    <h3 class="text-lg font-bold text-gray-900" x-text="editingId ? 'Edit Staff Member' : 'Add Staff Member'"></h3>
                    <button @click="showAddModal = false" class="p-1 text-gray-400 hover:text-gray-600">@heroicon('o-x-mark', 'w-5 h-5')</button>
                </div>
                <div class="flex-1 space-y-4 overflow-y-auto px-5 py-5 sm:px-6">
                    <div x-show="staffError" x-cloak class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700" x-text="staffError"></div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Full Name</label>
                        <input type="text" x-model="form.name" placeholder="e.g. John Mwangi"
                               class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none placeholder:text-gray-400">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Phone Number</label>
                        <input type="tel" x-model="form.phone" placeholder="+255 7XX XXX XXX"
                               class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none placeholder:text-gray-400">
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Role</label>
                            <select x-model="form.role" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none">
                                <template x-for="role in allRoles" :key="role.id">
                                    <option :value="role.id" x-text="role.label"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Counter</label>
                            <select x-model="form.counter_id" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none">
                                <option value="">(All)</option>
                                <template x-for="c in counters" :key="c.id">
                                    <option :value="c.id" x-text="c.name"></option>
                                </template>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Status</label>
                        <select x-model="form.status" class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 outline-none">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex gap-2">
                    <button @click="showAddModal = false" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white">Cancel</button>
                    <button :disabled="saving" @click="saveStaff()" class="flex-1 py-2.5 rounded-xl bg-blue-600 text-sm font-semibold text-white hover:bg-blue-700 shadow-sm shadow-blue-200 disabled:opacity-60" x-text="saving ? 'Saving...' : (editingId ? 'Save Changes' : 'Add Staff')"></button>
                </div>
            </div>
        </div>
    </template>

    
    <template x-teleport="body">
        <div x-show="showPermissionsModal"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             @click.away="showPermissionsModal = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showPermissionsModal = false"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg z-10 flex flex-col max-h-[90vh]"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <div class="min-w-0">
                        <h3 class="text-lg font-bold text-gray-900">Access &amp; Permissions</h3>
                        <p class="text-xs text-gray-500 truncate" x-text="selectedStaff ? selectedStaff.name : ''"></p>
                    </div>
                    <button @click="showPermissionsModal = false" class="p-1 text-gray-400 hover:text-gray-600">@heroicon('o-x-mark', 'w-5 h-5')</button>
                </div>
                <div class="px-6 py-4 overflow-y-auto space-y-2.5">
                    <p class="text-xs text-gray-400">Turn a module on to grant access, then fine-tune each part below it.</p>
                    <template x-for="mod in modules" :key="mod.key">
                        <div class="border border-gray-200 rounded-xl overflow-hidden">
                            <div class="flex items-center justify-between px-4 py-3" :class="isModuleEnabled(selectedStaff, mod.key) ? 'bg-blue-50/40' : 'bg-gray-50/60'">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="w-8 h-8 rounded-lg bg-white border border-gray-200 flex items-center justify-center text-xs font-bold text-gray-500 flex-shrink-0" x-text="mod.name.charAt(0)"></div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-900" x-text="mod.name"></p>
                                        <p class="text-[10px] text-gray-400" x-text="moduleComponentCount(selectedStaff, mod)"></p>
                                    </div>
                                </div>
                                <button @click="toggleModule(selectedStaff, mod)"
                                        :class="isModuleEnabled(selectedStaff, mod.key) ? 'bg-emerald-500' : 'bg-gray-300'"
                                        class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors duration-200 focus:outline-none flex-shrink-0">
                                    <span :class="isModuleEnabled(selectedStaff, mod.key) ? 'translate-x-4' : 'translate-x-0.5'"
                                          class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition-transform duration-200"></span>
                                </button>
                            </div>
                            <div x-show="isModuleEnabled(selectedStaff, mod.key)" x-transition.opacity class="divide-y divide-gray-50 border-t border-gray-100">
                                <template x-for="c in mod.components" :key="c.key">
                                    <div class="flex items-center justify-between px-4 py-2.5">
                                        <span class="text-xs text-gray-600" x-text="c.label"></span>
                                        <button @click="toggleComponent(selectedStaff, mod.key, c.key)"
                                                :class="componentEnabled(selectedStaff, mod.key, c.key) ? 'bg-blue-600' : 'bg-gray-200'"
                                                class="relative inline-flex h-4 w-8 items-center rounded-full transition-colors duration-200 focus:outline-none flex-shrink-0">
                                            <span :class="componentEnabled(selectedStaff, mod.key, c.key) ? 'translate-x-4' : 'translate-x-0.5'"
                                                  class="inline-block h-3 w-3 transform rounded-full bg-white shadow-sm transition-transform duration-200"></span>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between gap-2">
                    <span class="text-xs text-gray-400" x-text="selectedStaff ? (enabledModuleCount(selectedStaff) + ' of ' + modules.length + ' modules enabled') : ''"></span>
                    <button @click="showPermissionsModal = false" class="px-5 py-2.5 rounded-xl bg-blue-600 text-sm font-semibold text-white hover:bg-blue-700 shadow-sm shadow-blue-200">Done</button>
                </div>
            </div>
        </div>
    </template>

    
    <template x-teleport="body">
        <div x-show="showAdminAuthModal"
             x-cloak
             class="fixed inset-0 z-[60] flex items-center justify-center p-4"
             @click.away="closeAdminAuth()"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="closeAdminAuth()"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm z-10"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
                <div class="px-6 pt-6 pb-4 text-center">
                    <div class="inline-flex items-center justify-center w-14 h-14 bg-amber-100 rounded-full mb-4">
                        @heroicon('o-shield-check', 'w-7 h-7 text-amber-600')
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2" x-text="adminAuthTitle"></h3>
                    <p class="text-sm text-gray-500" x-text="adminAuthMessage"></p>
                </div>
                <div class="px-6 pb-5">
                    <label for="staff-admin-password" class="block text-sm font-medium text-gray-700 mb-1.5">Administrator password</label>
                    <input id="staff-admin-password"
                           type="password"
                           x-ref="adminAuthInput"
                           x-model="adminAuthPassword"
                           @input="adminAuthError = ''"
                           @keydown.enter.prevent="confirmAdminAuth()"
                           autocomplete="current-password"
                           placeholder="Enter your password"
                           class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none placeholder:text-gray-400">
                    <p x-show="adminAuthError" x-cloak class="mt-2 text-xs font-medium text-red-600" x-text="adminAuthError"></p>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex gap-2">
                    <button @click="closeAdminAuth()" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white">Cancel</button>
                    <button @click="confirmAdminAuth()" class="flex-1 py-2.5 rounded-xl bg-blue-600 text-sm font-semibold text-white hover:bg-blue-700 shadow-sm shadow-blue-200">Confirm</button>
                </div>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div x-show="showDeleteModal"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             @click.away="closeDeleteStaff()"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="closeDeleteStaff()"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm z-10"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
                <div class="px-6 py-5 text-center">
                    <div class="inline-flex items-center justify-center w-14 h-14 bg-red-100 rounded-full mb-4">
                        @heroicon('o-exclamation-triangle', 'w-7 h-7 text-red-600')
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Remove Staff Member</h3>
                    <p class="text-sm text-gray-500 mb-1">Are you sure you want to remove</p>
                    <p class="text-base font-bold text-gray-900" x-text="staffToDelete?.name"></p>
                    <p class="text-xs text-gray-400 mt-1">This action cannot be undone.</p>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex gap-2">
                    <button @click="closeDeleteStaff()" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white">Cancel</button>
                    <button @click="confirmDeleteStaff()" class="flex-1 py-2.5 rounded-xl bg-red-600 text-sm font-semibold text-white hover:bg-red-700 shadow-sm shadow-red-200">Remove</button>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection
