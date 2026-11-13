<div class="mx-auto max-w-6xl px-6 py-8">
    <div class="mb-4">
        <h1 class="text-lg font-semibold text-slate-800">Unresolved punches</h1>
        <p class="text-sm text-slate-500">Device user IDs with punches that are not linked to an employee.</p>
    </div>

    <div class="overflow-hidden rounded border bg-white">
        <table class="table-compact min-w-full text-left">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">Group</th>
                    <th class="px-3 py-2">Device user ID</th>
                    <th class="px-3 py-2">Count</th>
                    <th class="px-3 py-2">First</th>
                    <th class="px-3 py-2">Last</th>
                    <th class="px-3 py-2">Enrol</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($groups as $group)
                    @php
                        $from = \Illuminate\Support\Str::of((string) $group->first_at)->substr(0, 10)->toString();
                    @endphp
                    <tr class="border-t" wire:key="{{ $group->device_group }}-{{ $group->device_user_id }}">
                        <td class="px-3 py-2">{{ $group->device_group }}</td>
                        <td class="px-3 py-2 font-medium">{{ $group->device_user_id }}</td>
                        <td class="px-3 py-2">{{ $group->punch_count }}</td>
                        <td class="px-3 py-2">{{ $group->first_at }}</td>
                        <td class="px-3 py-2">{{ $group->last_at }}</td>
                        <td class="px-3 py-2">
                            @if ($employees->isNotEmpty())
                                <div class="flex flex-wrap items-center gap-2">
                                    <select
                                        class="rounded border px-2 py-1 text-sm"
                                        wire:key="emp-{{ $group->device_group }}-{{ $group->device_user_id }}"
                                        x-ref="emp"
                                    >
                                        @foreach ($employees as $employment)
                                            <option value="{{ $employment->id }}">
                                                {{ $employment->employee_no }} — {{ $employment->person->first_name }} {{ $employment->person->last_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button
                                        type="button"
                                        x-data
                                        x-on:click="$wire.enrol('{{ $group->device_group }}', {{ $group->device_user_id }}, Number($refs.emp.value), '{{ $from }}')"
                                        class="rounded bg-[var(--color-erp-accent)] px-2 py-1 text-sm text-white"
                                    >
                                        Enrol this ID to…
                                    </button>
                                </div>
                            @else
                                <span class="text-sm text-slate-400">No permitted employees</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr class="border-t">
                        <td colspan="6" class="px-3 py-4 text-sm text-slate-500">No unresolved punches.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
