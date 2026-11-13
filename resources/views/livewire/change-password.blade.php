<div class="w-full max-w-md">
    <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-lg">
        <div class="mb-6 text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center">
                <img src="{{ asset('images/ecw_logo3.png') }}" alt="ECW logo" class="max-h-16 max-w-full object-contain">
            </div>
            <h1 class="text-xl font-semibold text-slate-800">Change password</h1>
            <p class="mt-1 text-sm text-slate-500">Choose a new password of at least 10 characters.</p>
        </div>

        <form wire:submit="save" class="space-y-4">
            <div>
                <label for="password" class="mb-1 block text-xs font-medium text-slate-600">New password</label>
                <input
                    id="password"
                    type="password"
                    wire:model="password"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-800 focus:border-[var(--color-erp-accent)] focus:outline-none focus:ring-2 focus:ring-[var(--color-erp-accent)]/30"
                    autocomplete="new-password"
                >
                @error('password')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="mb-1 block text-xs font-medium text-slate-600">Confirm password</label>
                <input
                    id="password_confirmation"
                    type="password"
                    wire:model="password_confirmation"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-800 focus:border-[var(--color-erp-accent)] focus:outline-none focus:ring-2 focus:ring-[var(--color-erp-accent)]/30"
                    autocomplete="new-password"
                >
            </div>

            <button
                type="submit"
                class="w-full rounded-lg bg-[var(--color-erp-accent)] px-3 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[var(--color-erp-cyan)] focus:outline-none focus:ring-2 focus:ring-[var(--color-erp-accent)]/40"
            >
                Save password
            </button>
        </form>
    </div>

    <footer class="mt-6 text-center text-xs text-slate-500">
        <p>&copy; {{ date('Y') }} ECW Software Solutions. All rights reserved.</p>
    </footer>
</div>
