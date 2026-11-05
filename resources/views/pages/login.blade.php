<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login — BMS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .numpad-btn { -webkit-tap-highlight-color: transparent; user-select: none; }
        .numpad-btn:active { transform: scale(0.95); }
        .code-dot { transition: all 0.2s ease; }
    </style>
</head>
<body class="h-full bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 font-sans antialiased">

<div x-data="loginApp()" class="min-h-full flex flex-col items-center justify-center px-4">

    {{-- Logo --}}
    <div class="mb-8 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl shadow-lg shadow-blue-500/30 mb-4">
            <span class="text-white font-bold text-2xl tracking-tight">B</span>
        </div>
        <h1 class="text-white font-bold text-2xl tracking-tight">BMS</h1>
        <p class="text-sm text-slate-400 mt-1">Bar Management System</p>
    </div>

    @if (session('status'))
        <div class="mb-6 w-full max-w-[280px] rounded-xl border border-amber-400/30 bg-amber-400/10 px-4 py-3 text-center text-sm text-amber-200">
            {{ session('status') }}
        </div>
    @endif

    {{-- Code Display --}}
    <div class="mb-8">
        <div class="flex items-center justify-center gap-3">
            <template x-for="i in 4" :key="i">
                <div class="code-dot w-12 h-14 rounded-xl flex items-center justify-center text-2xl font-bold"
                     :class="code.length >= i ? 'bg-white/10 border-2 border-blue-500 text-white' : 'bg-white/5 border-2 border-white/10 text-white/30'"
                     x-text="code.length >= i ? '•' : ''">
                </div>
            </template>
        </div>
        <p x-show="error" x-cloak x-text="error" class="text-red-400 text-sm text-center mt-3"></p>
    </div>

    {{-- Number Pad --}}
    <div class="grid grid-cols-3 gap-3 w-full max-w-[280px]">
        <template x-for="n in [1,2,3,4,5,6,7,8,9]" :key="n">
            <button @click="addDigit(n)"
                    class="numpad-btn h-16 rounded-2xl bg-white/10 hover:bg-white/20 active:bg-white/25 text-white text-xl font-semibold backdrop-blur-sm transition-all duration-150 flex items-center justify-center"
                    x-text="n"></button>
        </template>
        <button @click="backspace()"
                class="numpad-btn h-16 rounded-2xl bg-white/5 hover:bg-white/10 text-white/60 flex items-center justify-center transition-all duration-150">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l-7-7 7-7m8 14l-7-7 7-7"/>
            </svg>
        </button>
        <button @click="addDigit(0)"
                class="numpad-btn h-16 rounded-2xl bg-white/10 hover:bg-white/20 active:bg-white/25 text-white text-xl font-semibold backdrop-blur-sm transition-all duration-150 flex items-center justify-center">
            0
        </button>
        <button @click="clearCode()"
                class="numpad-btn h-16 rounded-2xl bg-white/5 hover:bg-white/10 text-white/60 text-sm font-medium flex items-center justify-center transition-all duration-150">
            Clear
        </button>
    </div>

    {{-- Login Button --}}
    <button @click="login()"
            :disabled="code.length < 4 || loading"
            class="mt-6 w-full max-w-[280px] h-14 rounded-2xl text-base font-semibold transition-all duration-200 flex items-center justify-center gap-2"
            :class="code.length >= 4 && !loading ? 'bg-blue-600 hover:bg-blue-700 text-white shadow-lg shadow-blue-600/30' : 'bg-white/10 text-white/30 cursor-not-allowed'">
        <svg x-show="loading" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <span x-text="loading ? 'Signing in...' : 'Sign In'"></span>
    </button>

    {{-- Register link --}}
    <p class="mt-6 text-sm text-slate-500">
        Admin? <a href="{{ route('register') }}" class="text-blue-400 hover:text-blue-300 font-medium transition-colors">Register admin</a>
    </p>

</div>

<script>
function loginApp() {
    return {
        code: '',
        error: '',
        loading: false,
        csrf: document.querySelector('meta[name="csrf-token"]')
            ? document.querySelector('meta[name="csrf-token"]').content
            : '',

        addDigit(n) {
            if (this.loading) { return; }
            if (this.code.length < 4) {
                this.code += n;
                this.error = '';
                if (this.code.length === 4) {
                    this.login();
                }
            }
        },

        backspace() {
            this.code = this.code.slice(0, -1);
            this.error = '';
        },

        clearCode() {
            this.code = '';
            this.error = '';
        },

        async login() {
            if (this.code.length < 4 || this.loading) { return; }
            this.loading = true;
            this.error = '';

            try {
                const res = await fetch(@json(route('login.attempt')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    body: JSON.stringify({ pin: this.code }),
                });

                const data = await res.json().catch(function () { return {}; });

                if (!res.ok) {
                    this.loading = false;
                    this.code = '';
                    this.error = (data.errors && data.errors.pin && data.errors.pin[0])
                        || data.message
                        || 'Invalid code. Please try again.';
                    return;
                }

                window.location.href = data.redirect || @json(route('dashboard'));
            } catch (e) {
                this.loading = false;
                this.code = '';
                this.error = 'Could not sign in. Please check your connection and try again.';
            }
        },
    };
}
</script>

</body>
</html>
