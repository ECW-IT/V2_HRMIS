<x-layouts.app>
    <div class="mx-auto max-w-6xl px-6 py-8">
        <div class="mb-8">
            <h1 class="text-2xl font-semibold text-slate-800">ECW HRMIS</h1>
            <p class="mt-1 text-sm text-slate-500">
                Human resources home. Module screens will open as they are added.
            </p>
        </div>

        <div class="mb-10 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-b-4 border-b-[var(--color-erp-accent)] bg-white p-4 shadow-sm">
                <p class="text-sm font-semibold text-slate-600">Employees</p>
                <p class="mt-2 text-2xl font-bold text-slate-800">—</p>
                <p class="mt-1 text-xs text-slate-400">Available in a later task</p>
            </div>
            <div class="rounded-xl border border-b-4 border-b-emerald-500 bg-white p-4 shadow-sm">
                <p class="text-sm font-semibold text-slate-600">Attendance</p>
                <p class="mt-2 text-2xl font-bold text-slate-800">—</p>
                <p class="mt-1 text-xs text-slate-400">Available in a later task</p>
            </div>
            <div class="rounded-xl border border-b-4 border-b-amber-500 bg-white p-4 shadow-sm">
                <p class="text-sm font-semibold text-slate-600">Leave</p>
                <p class="mt-2 text-2xl font-bold text-slate-800">—</p>
                <p class="mt-1 text-xs text-slate-400">Available in a later task</p>
            </div>
        </div>

        @can('employee.view')
            <livewire:corehr.readiness-home-tile />
        @endcan

        <div>
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-400">Modules</h2>
            <div class="rounded-xl border bg-white p-6 shadow-sm">
                <x-module-launcher />
            </div>
        </div>
    </div>
</x-layouts.app>
