<!DOCTYPE html>
<html lang="en" class="h-full">
@php
    $activeNav = $activeNav ?? 'dashboard';
    $showGlobalSearch = $activeNav === 'pos';

    $barSettings = \App\Settings\BarSettings::all();
    $currency = \App\Settings\BarSettings::currency();
    $currencySymbol = $currency['symbol'];
    $currencyCode = $currency['code'];
    $currencyDecimals = $currency['decimals'];
    $barName = $barSettings['name'];
    $barLogo = \App\Settings\BarSettings::logoUrl();

    $currentUser = auth()->user();

    $adminProfile = [
        'name' => $currentUser?->name ?: 'Administrator',
        'email' => (string) ($currentUser?->email ?? ''),
        'phone' => (string) ($currentUser?->phone ?? $barSettings['phone']),
        'businessName' => (string) ($currentUser?->business_name ?? $barName),
        'role' => $currentUser ? ucfirst($currentUser->role ?? 'administrator') : 'Administrator',
        'joinDate' => $currentUser?->created_at ? $currentUser->created_at->format('M j, Y') : '',
        'initials' => $currentUser?->initials() ?: 'AD',
        'pin' => (string) ($currentUser?->pin ?? ''),
        'isAuthenticated' => (bool) $currentUser,
        'isAdministrator' => (bool) ($currentUser?->isAdministrator() ?? false),
    ];
@endphp
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — BMS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        // Shared unit catalogue. Managed in Settings > Menu & Categories.
        // Every unit picker (Kitchen raw materials, recipe output, Inventory
        // products) reads this same list so a new unit is available everywhere.
        window.BmsUnits = {
            key: 'bms.units',
            defaults: [
                'kg', 'g', 'mg', 'L', 'ml',
                'pcs', 'portion', 'serving', 'slice', 'cup', 'bowl',
                'bottle', 'can', 'packet', 'sachet', 'pack', 'box', 'tray',
                'bag', 'carton', 'crate', 'sack', 'bunch', 'dozen', 'Units'
            ],
            read: function () {
                try {
                    var raw = localStorage.getItem(this.key);
                    if (raw) {
                        var parsed = JSON.parse(raw);
                        if (Array.isArray(parsed)) return parsed;
                    }
                } catch (e) {}
                return this.defaults.slice();
            },
            write: function (units) {
                try { localStorage.setItem(this.key, JSON.stringify(units)); } catch (e) {}
            },
            merge: function (lists) {
                var merged = [];
                (lists || []).forEach(function (list) {
                    (list || []).forEach(function (unit) {
                        var value = String(unit || '').trim();
                        if (value && merged.indexOf(value) === -1) merged.push(value);
                    });
                });
                return merged;
            }
        };
    </script>
    @stack('head')
    <script>
        // Global money helper — the active currency comes from Settings > Receipt.
        window.BmsMoney = {
            symbol: @json($currencySymbol),
            code: @json($currencyCode),
            decimals: {{ (int) $currencyDecimals }},
            bar: {
                name: @json($barName),
                logo: @json($barLogo),
                address: @json($barSettings['address']),
                phone: @json($barSettings['phone']),
                receipt_header: @json($barSettings['receipt_header']),
                receipt_footer: @json($barSettings['receipt_footer']),
            },
            // Format an amount with the active currency symbol.
            fmt: function (v) {
                var n = Number(v || 0);
                var fixed = n.toFixed(this.decimals);
                var parts = fixed.split('.');
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                return this.symbol + ' ' + parts.join('.');
            },
        };

        // Administrator profile shown in the header and profile modal.
        window.BmsAdminProfile = @json($adminProfile);
        window.BmsProfileRoutes = {
            update: @json(route('profile.update')),
            pin: @json(route('profile.pin')),
            password: @json(route('profile.password')),
        };
        // Keep URLs out of the x-data attribute below. A JSON-encoded URL
        // contains double quotes, which would terminate the HTML attribute
        // and stop Alpine from initialising the whole page.
        window.BmsLoginUrl = @json(route('login'));
    </script>
</head>
<body class="h-full bg-gray-50 font-sans antialiased">
    @php
        $navItems = [
            ['key' => 'dashboard', 'module' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'o-squares-2x2'],
            ['key' => 'pos', 'module' => 'pos', 'label' => 'POS', 'icon' => 'o-credit-card'],
            ['key' => 'sales', 'module' => 'sales', 'label' => 'Sales', 'icon' => 'o-banknotes'],
            ['key' => 'kitchen', 'module' => 'kitchen', 'label' => 'Kitchen', 'icon' => 'o-fire'],
            ['key' => 'inventory', 'module' => 'inventory', 'label' => 'Inventory', 'icon' => 'o-cube'],
            ['key' => 'expenses', 'module' => 'expenses', 'label' => 'Profit & Expenses', 'icon' => 'o-currency-dollar'],
            ['key' => 'reports', 'module' => 'reports', 'component' => 'viewExport', 'label' => 'Reports', 'icon' => 'o-chart-bar'],
            ['key' => 'staff', 'module' => 'staff', 'label' => 'Staff', 'icon' => 'o-user-group'],
            ['key' => 'rooms', 'module' => 'rooms', 'component' => 'view', 'label' => 'Rooms', 'icon' => 'o-building-office-2'],
            ['key' => 'settings', 'module' => 'settings', 'component' => 'manage', 'label' => 'Settings', 'icon' => 'o-cog-6-tooth'],
        ];
        $navItems = array_values(array_filter($navItems, function ($item) use ($currentUser) {
            if ($item['key'] === 'settings') {
                return $currentUser?->isAdministrator() ?? false;
            }

            return $currentUser?->hasPermission($item['module'], $item['component'] ?? null) ?? false;
        }));
    @endphp

    <div x-data="{
        userMenuOpen: false,
        mobileSearchOpen: false,
        showProfileModal: false,
        profileTab: 'account',
        profileSaving: false,
        pinSaving: false,
        showPinConfirm: false,
        passwordSaving: false,
        roomsModuleEnabled: window.BmsRooms.enabled(),
        profile: Object.assign({}, window.BmsAdminProfile),
        editProfile: {
            name: window.BmsAdminProfile.name,
            email: window.BmsAdminProfile.email,
            phone: window.BmsAdminProfile.phone,
            businessName: window.BmsAdminProfile.businessName
        },
        passwordForm: { password: '', password_confirmation: '' },
        profileIsAuthenticated: window.BmsAdminProfile.isAuthenticated,
        profileError: '',
        profileNotice: '',
        profileErrors: {},
        pinError: '',
        pinNotice: '',
        passwordError: '',
        passwordNotice: '',
        passwordErrors: {},
        activeNav: '{{ $activeNav }}',
        setActive(key) { this.activeNav = key },
        csrfToken() {
            var meta = document.querySelector('meta[name=csrf-token]');
            return meta ? meta.content : '';
        },
        editInitials() {
            var name = (this.editProfile.name || '').trim();
            if (!name) return 'AD';
            var parts = name.split(/\s+/).filter(Boolean);
            if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
            return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
        },
        fieldError(errors, key) {
            var value = errors ? errors[key] : '';
            return Array.isArray(value) ? (value[0] || '') : (value || '');
        },
        async apiJson(url, method, body) {
            var response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken()
                },
                body: JSON.stringify(body || {})
            });
            var data = null;
            try { data = await response.json(); } catch (e) { data = {}; }
            if (response.status === 419) {
                // The session expired while the page was open. Return to sign in.
                window.location.href = window.BmsLoginUrl;
                throw new Error('Your session expired. Please sign in again.');
            }
            if (!response.ok) {
                var error = new Error((data && data.message) || 'Something went wrong. Please try again.');
                error.status = response.status;
                error.payload = data || {};
                throw error;
            }
            return data || {};
        },
        openProfile() {
            this.editProfile = {
                name: this.profile.name,
                email: this.profile.email,
                phone: this.profile.phone,
                businessName: this.profile.businessName
            };
            this.passwordForm = { password: '', password_confirmation: '' };
            this.profileTab = 'account';
            this.profileError = '';
            this.profileNotice = '';
            this.profileErrors = {};
            this.pinError = '';
            this.pinNotice = '';
            this.passwordError = '';
            this.passwordNotice = '';
            this.passwordErrors = {};
            this.showProfileModal = true;
            this.userMenuOpen = false;
        },
        async saveProfile() {
            if (this.profileSaving || !this.profileIsAuthenticated) return;
            this.profileSaving = true;
            this.profileError = '';
            this.profileNotice = '';
            this.profileErrors = {};
            try {
                var payload = {
                    name: this.editProfile.name,
                    email: this.editProfile.email,
                    phone: this.editProfile.phone
                };
                if (this.profile.isAdministrator) {
                    payload.business_name = this.editProfile.businessName;
                }

                var data = await this.apiJson(window.BmsProfileRoutes.update, 'PATCH', payload);
                this.profile.name = data.user.name;
                this.profile.email = data.user.email || '';
                this.profile.phone = data.user.phone || '';
                this.profile.businessName = data.user.business_name || '';
                this.profile.initials = data.user.initials || this.editInitials();
                this.profileNotice = data.message || 'Profile updated successfully.';
            } catch (e) {
                this.profileError = e.message;
                this.profileErrors = (e.payload && e.payload.errors) || {};
            } finally {
                this.profileSaving = false;
            }
        },
        async regeneratePin() {
            if (this.pinSaving || !this.profileIsAuthenticated) return;
            this.showPinConfirm = false;
            this.pinSaving = true;
            this.pinError = '';
            this.pinNotice = '';
            try {
                var data = await this.apiJson(window.BmsProfileRoutes.pin, 'POST', {});
                this.profile.pin = data.pin || '';
                this.pinNotice = 'Your new sign-in code is ' + (data.pin || '----') + '. Use it next time you sign in.';
            } catch (e) {
                this.pinError = e.message;
            } finally {
                this.pinSaving = false;
            }
        },
        async changePassword() {
            if (this.passwordSaving || !this.profileIsAuthenticated) return;
            this.passwordSaving = true;
            this.passwordError = '';
            this.passwordNotice = '';
            this.passwordErrors = {};
            try {
                var data = await this.apiJson(window.BmsProfileRoutes.password, 'POST', {
                    password: this.passwordForm.password,
                    password_confirmation: this.passwordForm.password_confirmation
                });
                this.passwordForm = { password: '', password_confirmation: '' };
                this.passwordNotice = data.message || 'Password changed successfully.';
            } catch (e) {
                this.passwordError = e.message;
                this.passwordErrors = (e.payload && e.payload.errors) || {};
            } finally {
                this.passwordSaving = false;
            }
        }
    }"
    class="min-h-full flex flex-col"
    @keydown.escape.window="userMenuOpen = false; mobileSearchOpen = false; showProfileModal = false"
    @bms-rooms-module-updated.window="roomsModuleEnabled = $event.detail.enabled">

        {{-- ===== TOP NAVIGATION ===== --}}
        <header class="fixed inset-x-0 top-0 z-40 bg-white/95 backdrop-blur-md border-b border-gray-200">
            <div class="flex items-center justify-between h-16 px-4 lg:px-6">

                {{-- Logo --}}
                <div class="flex items-center gap-2.5">
                    @if ($barLogo)
                        <img src="{{ $barLogo }}" alt="{{ $barName }}" class="w-8 h-8 rounded-lg object-cover shadow-sm shadow-blue-800/20">
                    @else
                        <span class="flex items-center justify-center w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-sm shadow-blue-800/20">
                            <span class="text-white font-bold text-sm tracking-tight">B</span>
                        </span>
                    @endif
                    <span class="text-base font-bold text-gray-900 tracking-tight hidden sm:block">BMS</span>
                </div>

                {{-- Search (desktop, POS only) --}}
                @if ($showGlobalSearch)
                    <div class="hidden md:flex flex-1 max-w-md mx-6 lg:mx-10">
                        <div class="relative w-full">
                            @heroicon('o-magnifying-glass', 'absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400')
                            <input type="text" placeholder="Search products, sales, staff..."
                                   @input="window.dispatchEvent(new CustomEvent('bms-pos-search', { detail: $event.target.value }))"
                                   class="w-full h-10 pl-10 pr-4 text-sm bg-gray-100 border border-transparent rounded-xl focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all duration-200 placeholder:text-gray-400">
                        </div>
                    </div>
                @endif

                {{-- Right actions --}}
                <div class="flex items-center gap-1.5">

                    @if ($showGlobalSearch)
                        {{-- Mobile search trigger (POS only) --}}
                        <button @click="mobileSearchOpen = !mobileSearchOpen"
                                class="md:hidden relative p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-xl transition-colors">
                            @heroicon('o-magnifying-glass', 'w-5 h-5')
                        </button>
                    @endif

                    {{-- Signed-in profile dropdown (available to every role) --}}
                    <div class="relative"
                         @keydown.escape.window="userMenuOpen = false">
                        <button @click="userMenuOpen = !userMenuOpen"
                                class="flex items-center gap-2 p-1.5 pr-2.5 hover:bg-gray-100 rounded-xl transition-colors"
                                :class="{ 'bg-gray-100': userMenuOpen }"
                                :title="profile.name">
                            <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center shadow-sm shadow-blue-200">
                                <span class="text-white text-sm font-semibold" x-text="profile.initials">{{ $currentUser?->initials() ?: 'A' }}</span>
                            </div>
                            <div class="hidden lg:block text-left max-w-[10rem]">
                                <p class="text-sm font-medium text-gray-900 leading-tight truncate" x-text="profile.name">{{ $currentUser?->name }}</p>
                                <p class="text-[11px] text-gray-500 leading-tight truncate" x-text="profile.role">{{ ucfirst($currentUser?->role ?? 'Administrator') }}</p>
                            </div>
                            @heroicon('o-chevron-down', 'hidden lg:block w-4 h-4 text-gray-400 transition-transform duration-200')
                        </button>

                        <div x-show="userMenuOpen"
                             x-cloak
                             @click.away="userMenuOpen = false"
                             class="absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden origin-top-right">
                            <div class="px-4 py-3 border-b border-gray-100 lg:hidden">
                                <p class="text-sm font-medium text-gray-900 truncate" x-text="profile.name">{{ $currentUser?->name }}</p>
                                <p class="text-xs text-gray-500 truncate" x-text="profile.email || profile.role">{{ $currentUser?->email ?: ucfirst($currentUser?->role ?? 'Administrator') }}</p>
                            </div>
                            <div class="py-1">
                                <button @click="openProfile()" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors w-full text-left">
                                    @heroicon('o-user-circle', 'w-4 h-4 text-gray-400')
                                    Profile
                                </button>
                                @if ($currentUser?->isAdministrator())
                                    <a href="/settings" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                        @heroicon('o-cog-6-tooth', 'w-4 h-4 text-gray-400')
                                        Settings
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Sign out stays available to every signed-in user. --}}
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="flex items-center gap-2 h-10 px-2 xl:px-3 text-red-600 bg-red-50/60 hover:bg-red-100 border border-red-100 hover:border-red-200 rounded-xl transition-colors"
                                title="Sign out" aria-label="Sign out">
                            @heroicon('o-arrow-left-end-on-rectangle', 'w-5 h-5')
                            <span class="hidden sm:inline text-sm font-medium">Sign out</span>
                        </button>
                    </form>

                </div>
            </div>

            @if ($showGlobalSearch)
                {{-- Mobile search bar (POS only) --}}
                <div x-show="mobileSearchOpen"
                     x-cloak
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 -translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="md:hidden px-4 pb-3">
                    <div class="relative">
                        @heroicon('o-magnifying-glass', 'absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400')
                        <input type="text" placeholder="Search..."
                               @input="window.dispatchEvent(new CustomEvent('bms-pos-search', { detail: $event.target.value }))"
                               class="w-full h-10 pl-10 pr-4 text-sm bg-gray-100 border border-transparent rounded-xl focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all duration-200 placeholder:text-gray-400">
                    </div>
                </div>
            @endif
        </header>

        {{-- ===== MAIN CONTENT ===== --}}
        <main class="flex-1 pt-16 pb-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                @yield('content')
            </div>
        </main>

        {{-- ===== FOOTER ===== --}}
        <footer class="border-t border-gray-200 bg-white py-3 px-6 text-center pb-20">
        </footer>

        {{-- ===== BOTTOM NAVIGATION (All screen sizes) ===== --}}
        <nav class="fixed inset-x-0 bottom-0 z-40 bg-white border-t border-gray-200 safe-bottom">
            <div class="flex items-stretch overflow-x-auto no-scrollbar">
                @foreach ($navItems as $item)
                    @php
                        $isActive = $activeNav === $item['key'];
                    @endphp
                    <a x-show="{{ $item['key'] === 'rooms' ? 'roomsModuleEnabled' : 'true' }}" href="{{ $item['key'] === 'dashboard' ? '/' : '/' . $item['key'] }}"
                       class="relative flex flex-col items-center justify-center flex-shrink-0 flex-1 min-w-[4.5rem] h-[68px] px-1.5 transition-all duration-150 group"
                       :class="activeNav === '{{ $item['key'] }}' ? 'text-blue-600' : 'text-gray-500 hover:text-gray-700'">
                        <span class="absolute inset-x-2 top-0 h-0.5 rounded-full transition-opacity duration-150"
                              :class="activeNav === '{{ $item['key'] }}' ? 'bg-blue-600 opacity-100' : 'bg-transparent opacity-0'"></span>
                        <div class="relative mb-0.5"
                             :class="activeNav === '{{ $item['key'] }}' ? 'scale-110' : ''"
                             style="transition: transform 0.15s ease">
                            @heroicon($item['icon'], 'w-[22px] h-[22px]')
                        </div>
                        <span class="text-[10px] leading-tight font-medium whitespace-nowrap"
                              :class="activeNav === '{{ $item['key'] }}' ? 'font-semibold' : ''">
                            {{ $item['label'] }}
                        </span>
                    </a>
                @endforeach
            </div>
        </nav>

    {{-- Profile modal (available to every signed-in role) --}}
    <template x-teleport="body">
        <div x-show="showProfileModal"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             @click.away="showProfileModal = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showProfileModal = false"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl z-10 overflow-hidden flex flex-col max-h-[90vh]"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">

                {{-- Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 flex-shrink-0">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">My Profile</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Manage your account details and sign-in security</p>
                    </div>
                    <button @click="showProfileModal = false" class="p-1 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition-colors">
                        @heroicon('o-x-mark', 'w-5 h-5')
                    </button>
                </div>

                {{-- Identity summary --}}
                <div class="px-6 py-5 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-white flex-shrink-0">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-200 flex-shrink-0">
                            <span class="text-white text-2xl font-bold" x-text="editInitials()"></span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h4 class="text-lg font-bold text-gray-900 truncate" x-text="editProfile.name || 'Your account'"></h4>
                            <p class="text-sm text-gray-500" x-text="profile.role"></p>
                            <p class="text-xs text-gray-400 mt-0.5" x-show="profile.joinDate">
                                Member since <span x-text="profile.joinDate"></span>
                            </p>
                        </div>
                        <span class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 flex-shrink-0">
                            @heroicon('o-shield-check', 'w-4 h-4')
                            <span x-text="profile.role"></span>
                        </span>
                    </div>
                </div>

                {{-- Tabs --}}
                <div class="flex gap-1 px-6 border-b border-gray-200 flex-shrink-0">
                    <button @click="profileTab = 'account'"
                            class="flex items-center gap-2 px-3 py-3 text-sm font-medium border-b-2 -mb-px transition-colors"
                            :class="profileTab === 'account' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
                        @heroicon('o-user-circle', 'w-4 h-4')
                        Account
                    </button>
                    <button @click="profileTab = 'security'"
                            class="flex items-center gap-2 px-3 py-3 text-sm font-medium border-b-2 -mb-px transition-colors"
                            :class="profileTab === 'security' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
                        @heroicon('o-lock-closed', 'w-4 h-4')
                        Security
                    </button>
                </div>

                {{-- Scrollable body --}}
                <div class="px-6 py-5 overflow-y-auto flex-1">

                    {{-- Guest state --}}
                    <div x-show="!profileIsAuthenticated" x-cloak class="mb-5 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                        <span class="text-amber-600 flex-shrink-0 mt-px">@heroicon('o-exclamation-triangle', 'w-5 h-5')</span>
                        <div>
                            <p class="text-sm font-semibold text-amber-900">You are not signed in</p>
                            <p class="text-xs text-amber-700 mt-0.5">
                                <a href="{{ route('login') }}" class="font-semibold underline underline-offset-2 hover:text-amber-900">Sign in</a>
                                to view and update your profile.
                            </p>
                        </div>
                    </div>

                    {{-- Account tab --}}
                    <div x-show="profileTab === 'account'" class="space-y-5">
                        <div x-show="profileError" x-cloak class="flex items-start gap-2.5 rounded-xl border border-red-200 bg-red-50 px-4 py-3">
                            <span class="text-red-600 flex-shrink-0 mt-px">@heroicon('o-exclamation-triangle', 'w-5 h-5')</span>
                            <p class="text-sm text-red-700" x-text="profileError"></p>
                        </div>
                        <div x-show="profileNotice" x-cloak class="flex items-start gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                            <span class="text-emerald-600 flex-shrink-0 mt-px">@heroicon('o-check-circle', 'w-5 h-5')</span>
                            <p class="text-sm text-emerald-700" x-text="profileNotice"></p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Full Name <span class="text-red-500">*</span></label>
                                <input type="text" x-model="editProfile.name" autocomplete="name"
                                       class="w-full h-10 px-4 text-sm border rounded-xl outline-none transition-all focus:ring-2"
                                       :class="fieldError(profileErrors, 'name') ? 'bg-red-50 border-red-400 focus:border-red-500 focus:ring-red-500/20' : 'bg-gray-50 border-gray-300 focus:border-blue-500 focus:ring-blue-500/20'">
                                <p x-show="fieldError(profileErrors, 'name')" x-cloak class="mt-1 text-xs text-red-600" x-text="fieldError(profileErrors, 'name')"></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Email Address <span class="text-red-500">*</span></label>
                                <input type="email" x-model="editProfile.email" autocomplete="email"
                                       class="w-full h-10 px-4 text-sm border rounded-xl outline-none transition-all focus:ring-2"
                                       :class="fieldError(profileErrors, 'email') ? 'bg-red-50 border-red-400 focus:border-red-500 focus:ring-red-500/20' : 'bg-gray-50 border-gray-300 focus:border-blue-500 focus:ring-blue-500/20'">
                                <p x-show="fieldError(profileErrors, 'email')" x-cloak class="mt-1 text-xs text-red-600" x-text="fieldError(profileErrors, 'email')"></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Phone Number</label>
                                <input type="tel" x-model="editProfile.phone" autocomplete="tel"
                                       class="w-full h-10 px-4 text-sm border rounded-xl outline-none transition-all focus:ring-2"
                                       :class="fieldError(profileErrors, 'phone') ? 'bg-red-50 border-red-400 focus:border-red-500 focus:ring-red-500/20' : 'bg-gray-50 border-gray-300 focus:border-blue-500 focus:ring-blue-500/20'">
                                <p x-show="fieldError(profileErrors, 'phone')" x-cloak class="mt-1 text-xs text-red-600" x-text="fieldError(profileErrors, 'phone')"></p>
                            </div>
                            <div class="sm:col-span-2" x-show="profile.isAdministrator">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Business Name</label>
                                <input type="text" x-model="editProfile.businessName" autocomplete="organization"
                                       class="w-full h-10 px-4 text-sm border rounded-xl outline-none transition-all focus:ring-2"
                                       :class="fieldError(profileErrors, 'business_name') ? 'bg-red-50 border-red-400 focus:border-red-500 focus:ring-red-500/20' : 'bg-gray-50 border-gray-300 focus:border-blue-500 focus:ring-blue-500/20'">
                                <p x-show="fieldError(profileErrors, 'business_name')" x-cloak class="mt-1 text-xs text-red-600" x-text="fieldError(profileErrors, 'business_name')"></p>
                                <p class="mt-1.5 text-[11px] text-gray-400">Used for the business profile and receipts across the system.</p>
                            </div>
                        </div>
                    </div>

                    {{-- Security tab --}}
                    <div x-show="profileTab === 'security'" class="space-y-5">
                        {{-- Sign-in code --}}
                        <div class="rounded-2xl border border-gray-200 overflow-hidden">
                            <div class="px-5 py-4 flex flex-col sm:flex-row sm:items-center gap-4 bg-gray-50/60">
                                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0">
                                    @heroicon('o-key', 'w-5 h-5')
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-sm font-semibold text-gray-900">Sign-in code</h4>
                                    <p class="text-xs text-gray-500 mt-0.5">The 4 digit code you use on the login screen.</p>
                                </div>
                                <div class="sm:text-right">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Current code</p>
                                    <p class="font-mono text-xl font-bold tracking-[0.35em] text-gray-900" x-text="profile.pin || '----'"></p>
                                </div>
                            </div>
                            <div class="px-5 py-4 border-t border-gray-100">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    <p class="text-xs text-gray-500">Generating a new code immediately replaces the current one.</p>
                                    <button @click="showPinConfirm = true"
                                            :disabled="pinSaving || !profileIsAuthenticated"
                                            class="inline-flex items-center justify-center gap-2 h-9 px-4 rounded-xl border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex-shrink-0">
                                        <span x-show="!pinSaving">Generate new code</span>
                                        <span x-show="pinSaving">Generating...</span>
                                    </button>
                                </div>
                                <div x-show="showPinConfirm" x-cloak class="mt-4 flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="text-xs text-amber-800">Your current 4 digit code will stop working immediately.</p>
                                    <div class="flex gap-2">
                                        <button type="button" @click="showPinConfirm = false" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                                        <button type="button" @click="regeneratePin()" class="rounded-lg bg-amber-600 px-3 py-2 text-xs font-semibold text-white hover:bg-amber-700">Confirm</button>
                                    </div>
                                </div>
                                <p x-show="pinNotice" x-cloak class="mt-3 text-xs font-medium text-emerald-600" x-text="pinNotice"></p>
                                <p x-show="pinError" x-cloak class="mt-3 text-xs font-medium text-red-600" x-text="pinError"></p>
                            </div>
                        </div>

                        {{-- Password --}}
                        <div class="rounded-2xl border border-gray-200 overflow-hidden">
                            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center flex-shrink-0">
                                    @heroicon('o-lock-closed', 'w-5 h-5')
                                </div>
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900">Password</h4>
                                    <p class="text-xs text-gray-500 mt-0.5">Update the password attached to your account.</p>
                                </div>
                            </div>
                            <div class="px-5 py-4 space-y-4">
                                <div x-show="passwordError" x-cloak class="flex items-start gap-2.5 rounded-xl border border-red-200 bg-red-50 px-4 py-3">
                                    <span class="text-red-600 flex-shrink-0 mt-px">@heroicon('o-exclamation-triangle', 'w-5 h-5')</span>
                                    <p class="text-sm text-red-700" x-text="passwordError"></p>
                                </div>
                                <div x-show="passwordNotice" x-cloak class="flex items-start gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                                    <span class="text-emerald-600 flex-shrink-0 mt-px">@heroicon('o-check-circle', 'w-5 h-5')</span>
                                    <p class="text-sm text-emerald-700" x-text="passwordNotice"></p>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">New Password <span class="text-red-500">*</span></label>
                                        <input type="password" x-model="passwordForm.password" autocomplete="new-password"
                                               class="w-full h-10 px-4 text-sm border rounded-xl outline-none transition-all focus:ring-2"
                                               :class="fieldError(passwordErrors, 'password') ? 'bg-red-50 border-red-400 focus:border-red-500 focus:ring-red-500/20' : 'bg-gray-50 border-gray-300 focus:border-blue-500 focus:ring-blue-500/20'">
                                        <p x-show="fieldError(passwordErrors, 'password')" x-cloak class="mt-1 text-xs text-red-600" x-text="fieldError(passwordErrors, 'password')"></p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirm New Password <span class="text-red-500">*</span></label>
                                        <input type="password" x-model="passwordForm.password_confirmation" autocomplete="new-password"
                                               class="w-full h-10 px-4 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                                    </div>
                                </div>
                                <div class="flex justify-end">
                                    <button @click="changePassword()"
                                            :disabled="passwordSaving || !profileIsAuthenticated"
                                            class="inline-flex items-center justify-center gap-2 h-10 px-5 rounded-xl bg-blue-600 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-sm shadow-blue-200">
                                        <span x-show="!passwordSaving">Update password</span>
                                        <span x-show="passwordSaving">Updating...</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between gap-3 flex-shrink-0">
                    @if ($currentUser?->isAdministrator())
                        <a href="{{ route('settings') }}" class="text-xs font-medium text-gray-500 hover:text-gray-700 transition-colors">
                            Business settings
                        </a>
                    @else
                        <span aria-hidden="true"></span>
                    @endif
                    <div class="flex items-center gap-2">
                        <button @click="showProfileModal = false" class="py-2.5 px-4 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-white transition-colors">Close</button>
                        <button x-show="profileTab === 'account'"
                                @click="saveProfile()"
                                :disabled="profileSaving || !profileIsAuthenticated"
                                class="inline-flex items-center justify-center gap-2 py-2.5 px-5 rounded-xl bg-blue-600 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-sm shadow-blue-200">
                            <span x-show="!profileSaving">Save changes</span>
                            <span x-show="profileSaving">Saving...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
    </div>

    @stack('scripts')
</body>
</html>
