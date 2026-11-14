<div class="mx-auto max-w-[1280px] px-6 py-4">
    @if(session('success'))
        <div class="mb-4 rounded bg-green-100 px-4 py-2 text-sm text-green-700">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded bg-red-100 px-4 py-2 text-sm text-red-700">{{ session('error') }}</div>
    @endif
    @if($flash !== '')
        <div class="mb-4 rounded bg-green-100 px-4 py-2 text-sm text-green-700">{{ $flash }}</div>
    @endif

    <div class="mb-2 flex items-center gap-3 p-2">
        <a href="{{ route('employees.index') }}" class="flex items-center gap-1 text-sm text-blue-600 hover:underline">&larr; Employees</a>
    </div>

    @if($pendingCount > 0)
        <div class="mb-4 rounded border border-yellow-300 bg-yellow-50 px-4 py-2 text-sm text-yellow-800">
            @if($pendingCount === 1)
                1 change is waiting for approval.
            @else
                {{ $pendingCount }} changes are waiting for approval.
            @endif
            @can('approval.act')
                <a href="{{ route('approvals.index') }}" class="ml-2 font-medium underline">View requests</a>
            @endcan
        </div>
    @endif

    {{-- Header --}}
    <div class="mb-4 rounded bg-white p-5 shadow">
        <div class="flex flex-wrap items-start gap-4">
            <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-[var(--color-erp-accent-soft)] text-xl font-semibold text-[var(--color-erp-accent)]">
                {{ mb_strtoupper(mb_substr($employment->person->first_name, 0, 1).mb_substr($employment->person->last_name, 0, 1)) }}
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-lg font-semibold text-gray-800">{{ $employment->person->name_with_initials }}</h1>
                    @include('corehr::partials.type-badge', ['type' => $employment->type])
                    @include('corehr::partials.status-badge', ['status' => $employment->status])
                </div>
                <p class="mt-1 text-sm text-gray-600">
                    <span class="font-mono text-xs">{{ $employment->employee_no }}</span>
                    · {{ $employment->company->name }}
                </p>
                <p class="text-sm text-gray-600">
                    {{ $assignment?->job?->name ?? '—' }}
                    · {{ $assignment?->orgUnit?->name ?? '—' }}
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    Hired {{ $employment->hire_date->format('Y-m-d') }} · Length of service {{ $service }}
                    @if($employment->exit_date)
                        · Left {{ $employment->exit_date->format('Y-m-d') }}
                    @endif
                </p>
            </div>

            @if($actions !== [])
                <div class="flex flex-wrap items-center gap-2">
                    @foreach($actions as $key => $label)
                        <button type="button" wire:click="openAction('{{ $key }}')" wire:loading.attr="disabled" wire:target="openAction"
                            @class([
                                'rounded px-3 py-1.5 text-sm font-medium disabled:opacity-50',
                                'border border-red-600 text-red-600 hover:bg-red-50' => $key === 'exit',
                                'border border-blue-600 text-blue-600 hover:bg-blue-50' => $key !== 'exit' && $key !== 'confirm',
                                'bg-blue-600 text-white hover:bg-blue-700' => $key === 'confirm',
                            ])>
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Tabs --}}
    <div class="mb-4 flex flex-wrap gap-1 border-b">
        @foreach($tabs as $key => $label)
            <button type="button" wire:click="selectTab('{{ $key }}')"
                @class([
                    'border-b-2 px-4 py-2 text-sm font-medium',
                    'border-blue-600 text-blue-700' => $tab === $key,
                    'border-transparent text-gray-600 hover:text-gray-800' => $tab !== $key,
                ])>
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div>
        @switch($tab)
            @case('summary')
                <livewire:corehr.tabs.summary :employment="$employment" :key="'summary-'.$employment->id" />
                @break
            @case('personal')
                <livewire:corehr.tabs.personal :employment="$employment" :key="'personal-'.$employment->id" />
                @break
            @case('history')
                <livewire:corehr.tabs.employment-history :employment="$employment" :key="'history-'.$employment->id" />
                @break
            @case('bank')
                <livewire:corehr.tabs.bank :employment="$employment" :key="'bank-'.$employment->id" />
                @break
            @case('documents')
                <livewire:corehr.tabs.documents :employment="$employment" :key="'documents-'.$employment->id" />
                @break
            @case('attendance')
                <livewire:corehr.tabs.attendance :employment="$employment" :key="'attendance-'.$employment->id" />
                @break
            @case('leave')
                <livewire:leave.employee-leave :employment="$employment" :key="'leave-'.$employment->id" />
                @break
            @case('time')
                <livewire:attendance.tabs.time-profile :employment="$employment" :key="'time-'.$employment->id" />
                @break
            @case('readiness')
                <livewire:corehr.tabs.readiness :employment="$employment" :key="'readiness-'.$employment->id" />
                @break
            @case('audit')
                <livewire:corehr.tabs.audit :employment="$employment" :key="'audit-'.$employment->id" />
                @break
        @endswitch
    </div>

    {{-- Action dialog --}}
    @if($action !== null)
        <div class="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto bg-black/40 p-6" wire:key="action-dialog">
            <div class="popup mt-10 w-full max-w-2xl bg-white p-5 shadow-xl">
                @switch($action)
                    @case('change-assignment')
                        <livewire:corehr.actions.change-assignment :employment="$employment" :key="'act-change-'.$employment->id" />
                        @break
                    @case('confirm')
                        <livewire:corehr.actions.confirm-employment :employment="$employment" :key="'act-confirm-'.$employment->id" />
                        @break
                    @case('suspend')
                        <livewire:corehr.actions.suspend :employment="$employment" :key="'act-suspend-'.$employment->id" />
                        @break
                    @case('reinstate')
                        <livewire:corehr.actions.reinstate :employment="$employment" :key="'act-reinstate-'.$employment->id" />
                        @break
                    @case('sister-transfer')
                        <livewire:corehr.actions.sister-transfer :employment="$employment" :key="'act-transfer-'.$employment->id" />
                        @break
                    @case('exit')
                        <livewire:corehr.actions.exit-employment :employment="$employment" :key="'act-exit-'.$employment->id" />
                        @break
                @endswitch
            </div>
        </div>
    @endif
</div>
