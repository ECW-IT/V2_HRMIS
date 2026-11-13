<div class="mb-10">
    <a
        href="{{ route('employees.readiness') }}"
        class="block rounded-xl border border-b-4 border-b-red-500 bg-white p-4 shadow-sm transition hover:bg-slate-50"
    >
        <p class="text-sm font-semibold text-slate-600">Payroll readiness</p>
        <p class="mt-2 text-lg font-bold text-slate-800">
            {{ $blockers }} blockers, {{ $warnings }} warnings
        </p>
        <p class="mt-1 text-xs text-slate-400">Open readiness dashboard</p>
    </a>
</div>
