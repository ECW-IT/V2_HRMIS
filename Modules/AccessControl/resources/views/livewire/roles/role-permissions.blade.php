<div class="p-4">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-[var(--color-erp-accent)] px-4 py-3 text-white">
        <h1 class="text-base font-semibold">Role : {{ $roleName }}</h1>
        <a
            href="{{ route('access-control.roles.index') }}"
            class="rounded bg-red-600 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-red-500"
        >
            ← Back
        </a>
    </div>

    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 class="text-lg font-semibold text-slate-800">Permissions</h2>
        @if ($canManage)
            <button
                type="button"
                wire:click="save"
                class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90"
            >
                Save permissions
            </button>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($groups as $group => $permissions)
            <div class="overflow-hidden rounded border bg-white shadow-sm">
                <div class="bg-[var(--color-erp-accent)] px-3 py-2 text-sm font-semibold capitalize text-white">
                    {{ str_replace('-', ' ', $group) }}
                </div>
                <div class="space-y-2 p-3">
                    @foreach ($permissions as $permission)
                        <label class="flex items-start gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                value="{{ $permission->name }}"
                                wire:model="selected"
                                class="mt-0.5 rounded border-slate-300 text-[var(--color-erp-accent)] focus:ring-[var(--color-erp-accent)]"
                            >
                            <span class="font-mono text-xs">{{ $permission->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
