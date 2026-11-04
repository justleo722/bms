<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Register Admin — BMS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 font-sans antialiased">

<div x-data="registerApp()" class="min-h-full flex flex-col items-center justify-center px-4 py-8">

    {{-- Logo --}}
    <div class="mb-8 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl shadow-lg shadow-blue-500/30 mb-4">
            <span class="text-white font-bold text-2xl tracking-tight">B</span>
        </div>
        <h1 class="text-2xl font-bold text-white tracking-tight">Register Admin</h1>
        <p class="text-sm text-slate-400 mt-1">Create your admin account</p>
    </div>

    {{-- Success State --}}
    <div x-show="registered" x-cloak class="w-full max-w-sm">
        <div class="bg-white/10 backdrop-blur-md rounded-3xl p-8 border border-white/10 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-emerald-500/20 rounded-full mb-4">
                <svg class="w-8 h-8 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h2 class="text-xl font-bold text-white mb-2">Admin Registered!</h2>
            <p class="text-sm text-slate-400 mb-6">Your account has been created successfully.</p>

            {{-- The generated sign-in code --}}
            <div class="rounded-2xl bg-white/5 border border-white/10 px-4 py-5 mb-6">
                <p class="text-xs uppercase tracking-wider text-slate-400 mb-2">Your sign-in code</p>
                <p class="text-3xl font-bold tracking-[0.4em] text-white font-mono" x-text="pin"></p>
                <p class="text-[11px] text-slate-400 mt-3 leading-relaxed">
                    Write this down. It is what you type on the sign-in screen to log in.
                    You can change it later from Profile. We have also sent it to your email.
                </p>
            </div>

            <div x-show="emailWarnings.length" x-cloak class="mb-5 rounded-xl border border-amber-400/30 bg-amber-500/10 px-4 py-3 text-left">
                <p class="text-xs font-semibold text-amber-300">Registration succeeded, but an email could not be sent:</p>
                <ul class="mt-1 list-disc pl-4 text-xs text-amber-200">
                    <template x-for="warning in emailWarnings" :key="warning">
                        <li x-text="warning"></li>
                    </template>
                </ul>
            </div>

            <div class="flex gap-3 mt-4">
                <button @click="resetForm()" class="flex-1 h-12 rounded-xl bg-white/10 hover:bg-white/20 text-white text-sm font-semibold transition-all">
                    Register Another
                </button>
                <a :href="dashboardUrl" class="flex-1 h-12 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold transition-all flex items-center justify-center">
                    Go to Dashboard
                </a>
            </div>
        </div>
    </div>

    {{-- Registration Form --}}
    <div x-show="!registered" x-cloak class="w-full max-w-lg">
        <div class="bg-white/10 backdrop-blur-md rounded-3xl p-8 border border-white/10">
            <form @submit.prevent="register()">

                @if ($initialCodeEmailError)
                    <div class="mb-5 rounded-xl border border-amber-400/30 bg-amber-500/10 px-4 py-3 text-xs text-amber-200">
                        {{ $initialCodeEmailError }}
                    </div>
                @endif

                {{-- Entry Code --}}
                <div class="mb-5">
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">
                        Entry Code <span class="text-red-400">*</span>
                    </label>
                    <div class="relative">
                        <svg class="w-5 h-5 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 pointer-events-none"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                        </svg>
                        <input type="text" x-model="form.entryCode" required
                               autocomplete="off" spellcheck="false" placeholder="e.g. BMS-AB12CD34"
                               class="w-full h-12 pl-11 pr-4 text-sm bg-white/5 border border-white/10 rounded-xl text-white placeholder:text-white/30 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all uppercase tracking-widest"
                               :class="errors.entry_code ? 'border-red-400/70' : ''">
                    </div>
                    <p x-show="errors.entry_code" x-cloak class="text-red-400 text-xs mt-1.5" x-text="errors.entry_code ? errors.entry_code[0] : ''"></p>
                    <p x-show="!errors.entry_code" class="text-[11px] text-slate-500 mt-1.5 leading-relaxed">
                        This single-use code comes from the person who sold you the system. Each code works for one registration only.
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- Full Name --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Full Name</label>
                        <input type="text" x-model="form.name" required placeholder="e.g. John Mwaipopo"
                               class="w-full h-12 px-4 text-sm bg-white/5 border border-white/10 rounded-xl text-white placeholder:text-white/30 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"
                               :class="errors.name ? 'border-red-400/70' : ''">
                        <p x-show="errors.name" x-cloak class="text-red-400 text-xs mt-1" x-text="errors.name ? errors.name[0] : ''"></p>
                    </div>

                    {{-- Business Name --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Business Name</label>
                        <input type="text" x-model="form.businessName" required placeholder="e.g. Sunset Bar & Grill"
                               class="w-full h-12 px-4 text-sm bg-white/5 border border-white/10 rounded-xl text-white placeholder:text-white/30 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"
                               :class="errors.business_name ? 'border-red-400/70' : ''">
                        <p x-show="errors.business_name" x-cloak class="text-red-400 text-xs mt-1" x-text="errors.business_name ? errors.business_name[0] : ''"></p>
                    </div>

                    {{-- Phone Number --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Phone Number</label>
                        <input type="tel" x-model="form.phone" required placeholder="+255 712 345 678"
                               class="w-full h-12 px-4 text-sm bg-white/5 border border-white/10 rounded-xl text-white placeholder:text-white/30 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"
                               :class="errors.phone ? 'border-red-400/70' : ''">
                        <p x-show="errors.phone" x-cloak class="text-red-400 text-xs mt-1" x-text="errors.phone ? errors.phone[0] : ''"></p>
                    </div>

                    {{-- Email --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Email</label>
                        <input type="email" x-model="form.email" required placeholder="admin@sunsetbar.com"
                               class="w-full h-12 px-4 text-sm bg-white/5 border border-white/10 rounded-xl text-white placeholder:text-white/30 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"
                               :class="errors.email ? 'border-red-400/70' : ''">
                        <p x-show="errors.email" x-cloak class="text-red-400 text-xs mt-1" x-text="errors.email ? errors.email[0] : ''"></p>
                    </div>

                    {{-- Password --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Password</label>
                        <input type="password" x-model="form.password" required placeholder="Min. 8 characters"
                               class="w-full h-12 px-4 text-sm bg-white/5 border border-white/10 rounded-xl text-white placeholder:text-white/30 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"
                               :class="errors.password ? 'border-red-400/70' : ''">
                        <p x-show="errors.password" x-cloak class="text-red-400 text-xs mt-1" x-text="errors.password ? errors.password[0] : ''"></p>
                    </div>

                    {{-- Confirm Password --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Confirm Password</label>
                        <input type="password" x-model="form.confirmPassword" required placeholder="Re-enter password"
                               class="w-full h-12 px-4 text-sm bg-white/5 border border-white/10 rounded-xl text-white placeholder:text-white/30 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"
                               :class="(errors.password_confirmation || (form.confirmPassword && form.password !== form.confirmPassword)) ? 'border-red-400' : ''">
                        <p x-show="form.confirmPassword && form.password !== form.confirmPassword" class="text-red-400 text-xs mt-1">Passwords do not match</p>
                        <p x-show="errors.password_confirmation" x-cloak class="text-red-400 text-xs mt-1" x-text="errors.password_confirmation ? errors.password_confirmation[0] : ''"></p>
                    </div>
                </div>

                {{-- General error --}}
                <div x-show="error" x-cloak class="mt-5 px-4 py-3 rounded-xl bg-red-500/10 border border-red-400/30 text-sm text-red-300" x-text="error"></div>

                {{-- Submit --}}
                <button type="submit"
                        :disabled="loading || (form.password !== form.confirmPassword)"
                        class="w-full h-12 mt-6 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold transition-all flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg x-show="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span x-text="loading ? 'Creating account...' : 'Register Admin'"></span>
                </button>

            </form>
        </div>

        {{-- Back to login --}}
        <p class="mt-6 text-center text-sm text-slate-500">
            <a href="/login" class="text-blue-400 hover:text-blue-300 font-medium transition-colors">&larr; Back to Login</a>
        </p>
    </div>

</div>

<script>
function registerApp() {
    return {
        registered: false,
        loading: false,
        error: '',
        errors: {},
        pin: '',
        emailWarnings: [],
        dashboardUrl: '/',
        csrf: document.querySelector('meta[name="csrf-token"]')
            ? document.querySelector('meta[name="csrf-token"]').content
            : '',
        form: {
            entryCode: '',
            name: '',
            businessName: '',
            phone: '',
            email: '',
            password: '',
            confirmPassword: '',
        },

        async register() {
            if (this.form.password !== this.form.confirmPassword) {
                this.error = 'The two passwords do not match.';
                return;
            }

            this.loading = true;
            this.error = '';
            this.errors = {};

            try {
                const res = await fetch(@json(route('register.store')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    body: JSON.stringify({
                        entry_code: this.form.entryCode,
                        name: this.form.name,
                        business_name: this.form.businessName,
                        phone: this.form.phone,
                        email: this.form.email,
                        password: this.form.password,
                        password_confirmation: this.form.confirmPassword,
                    }),
                });

                const data = await res.json();

                if (!res.ok) {
                    this.errors = data.errors || {};
                    this.error = data.message
                        || (this.errors.entry_code
                            ? this.errors.entry_code[0]
                            : 'Your account could not be created. Please check the form and try again.');
                    this.loading = false;
                    return;
                }

                this.pin = data.pin || '';
                this.emailWarnings = data.email_warnings || [];
                this.dashboardUrl = data.redirect || '/';
                this.registered = true;
            } catch (e) {
                this.error = 'Network problem — your account could not be created.';
            } finally {
                this.loading = false;
            }
        },

        resetForm() {
            this.registered = false;
            this.pin = '';
            this.emailWarnings = [];
            this.error = '';
            this.errors = {};
            this.form = { entryCode: '', name: '', businessName: '', phone: '', email: '', password: '', confirmPassword: '' };
        },
    };
}
</script>

</body>
</html>
