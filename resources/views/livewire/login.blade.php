<div class="w-full max-w-md">
    <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-lg">
        <div class="mb-6 text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center">
                <img src="{{ asset('images/ecw_logo3.png') }}" alt="ECW logo" class="max-h-16 max-w-full object-contain">
            </div>
            <h1 class="text-xl font-semibold text-slate-800">ECW HR Management</h1>
            <p class="mt-1 text-sm text-slate-500">Sign in to your account</p>
        </div>

        <form wire:submit="login" class="space-y-4">
            <div>
                <label for="email" class="mb-1 block text-xs font-medium text-slate-600">Email address</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect width="20" height="16" x="2" y="4" rx="2" />
                            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
                        </svg>
                    </span>
                    <input
                        id="email"
                        type="email"
                        wire:model="email"
                        placeholder="Enter your email"
                        class="w-full rounded-lg border border-slate-300 py-2.5 pl-10 pr-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[var(--color-erp-accent)] focus:outline-none focus:ring-2 focus:ring-[var(--color-erp-accent)]/30"
                        autocomplete="username"
                        autofocus
                    >
                </div>
                @error('email')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div x-data="{ show: false }">
                <label for="password" class="mb-1 block text-xs font-medium text-slate-600">Password</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect width="18" height="11" x="3" y="11" rx="2" ry="2" />
                            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                        </svg>
                    </span>
                    <input
                        id="password"
                        :type="show ? 'text' : 'password'"
                        wire:model="password"
                        placeholder="Enter your password"
                        class="w-full rounded-lg border border-slate-300 py-2.5 pl-10 pr-10 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[var(--color-erp-accent)] focus:outline-none focus:ring-2 focus:ring-[var(--color-erp-accent)]/30"
                        autocomplete="current-password"
                    >
                    <button
                        type="button"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600"
                        @click="show = !show"
                        :aria-label="show ? 'Hide password' : 'Show password'"
                    >
                        <svg x-show="!show" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                        <svg x-show="show" x-cloak class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                            <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
                            <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
                            <line x1="2" x2="22" y1="2" y2="22" />
                        </svg>
                    </button>
                </div>
                @error('password')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button
                type="submit"
                class="w-full rounded-lg bg-[var(--color-erp-accent)] px-3 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[var(--color-erp-cyan)] focus:outline-none focus:ring-2 focus:ring-[var(--color-erp-accent)]/40"
            >
                Sign in
            </button>
        </form>

        <p class="mt-5 text-center text-xs text-slate-400">
            Need help? Contact your system administrator
        </p>
    </div>

    <footer class="mt-6 text-center text-xs text-slate-500">
        <p>&copy; {{ date('Y') }} ECW Software Solutions. All rights reserved.</p>
        <p class="mt-1 text-slate-400">Powered by HR Management System v2</p>
    </footer>
</div>
