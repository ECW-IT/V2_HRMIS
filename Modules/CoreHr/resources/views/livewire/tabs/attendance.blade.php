<div class="space-y-4">
    <div class="overflow-x-auto rounded bg-white shadow">
        <div class="flex items-center justify-between border-b px-5 py-3">
            <h3 class="text-sm font-semibold text-gray-700">Fingerprint enrolments</h3>
            @can('attendance.device.manage')
                @if(! $showEnrol && $employment->status !== \Modules\CoreHr\Enums\EmploymentStatus::EXITED)
                    <button type="button" wire:click="openEnrol" class="rounded border border-blue-600 px-3 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50">Enrol</button>
                @endif
            @endcan
        </div>
        <table class="w-full table-compact text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Device group</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Device user ID</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">From</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">To</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($enrolments as $enrolment)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-1.5 font-mono text-xs text-gray-700">{{ $enrolment->device_group }}</td>
                        <td class="px-3 py-1.5 font-mono text-xs text-gray-700">{{ $enrolment->device_user_id }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-600">{{ $enrolment->valid_from->format('Y-m-d') }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-600">{{ $enrolment->valid_to?->format('Y-m-d') ?? 'Present' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-3 py-6 text-center text-sm text-gray-500">Not enrolled in any device group.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($showEnrol)
        <livewire:corehr.actions.enrol :employment="$employment" :key="'enrol-'.$employment->id" />
    @endif

    <div class="rounded bg-white shadow">
        <div class="flex items-center justify-between border-b px-5 py-3">
            <h3 class="text-sm font-semibold text-gray-700">Punches</h3>
            <input type="month" wire:model.live="month" aria-label="Month"
                class="rounded border px-3 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500" />
        </div>
        @livewire('attendance.employee-punches', ['employmentId' => $employment->id, 'month' => $month], key('punches-'.$employment->id.'-'.$month))
    </div>

    @can('timecard.view')
        <livewire:attendance.employee-timecards :employment="$employment" :month="$month" :embedded="true" :key="'timecards-'.$employment->id.'-'.$month" />
    @endcan
</div>
