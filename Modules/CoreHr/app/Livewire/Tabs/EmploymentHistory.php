<?php

namespace Modules\CoreHr\Livewire\Tabs;

use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;
use Modules\CoreHr\Enums\EventType;
use Modules\CoreHr\Models\Assignment;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Models\EmploymentEvent;
use Modules\Organisation\Models\Grade;
use Modules\Organisation\Models\Job;
use Modules\Organisation\Models\Location;
use Modules\Organisation\Models\OrgUnit;

class EmploymentHistory extends Component
{
    private const FIELD_LABELS = [
        'org_unit_id' => 'Department / section',
        'job_id' => 'Job',
        'grade_id' => 'Grade',
        'location_id' => 'Location',
        'manager_employment_id' => 'Reports to',
    ];

    public Employment $employment;

    public function mount(Employment $employment): void
    {
        $this->employment = $employment;
    }

    public function render(): View
    {
        $this->authorize('employee.view');

        $events = $this->employment->events()
            ->with(['creator', 'approvalRequest.requester', 'relatedEmployment'])
            ->get();

        $assignments = $this->employment->assignments()
            ->with(['orgUnit.parent', 'job', 'grade', 'location', 'manager.person'])
            ->orderByDesc('effective_from')
            ->get();

        $names = $this->nameLookups($events);

        $timeline = $events
            ->map(fn (EmploymentEvent $event) => $this->eventRow($event, $assignments, $names))
            ->concat($this->assignmentsWithoutEvents($events, $assignments))
            ->sort(fn (array $a, array $b) => [$b['date'], $b['order']] <=> [$a['date'], $a['order']])
            ->values();

        return view('corehr::livewire.tabs.employment-history', [
            'timeline' => $timeline,
            'assignments' => $assignments,
        ]);
    }

    /**
     * @param  Collection<int, EmploymentEvent>  $events
     * @return array<string, array<int, string>>
     */
    private function nameLookups(Collection $events): array
    {
        $ids = [];
        foreach ($events as $event) {
            foreach (['from', 'to'] as $side) {
                foreach ((array) ($event->details[$side] ?? []) as $field => $value) {
                    if ($value !== null && isset(self::FIELD_LABELS[$field])) {
                        $ids[$field][] = (int) $value;
                    }
                }
            }
        }

        $lookup = fn (string $field, string $model, string $column = 'name') => isset($ids[$field])
            ? $model::query()->whereIn('id', array_unique($ids[$field]))->pluck($column, 'id')->all()
            : [];

        return [
            'org_unit_id' => $lookup('org_unit_id', OrgUnit::class),
            'job_id' => $lookup('job_id', Job::class),
            'grade_id' => $lookup('grade_id', Grade::class),
            'location_id' => $lookup('location_id', Location::class),
            'manager_employment_id' => $lookup('manager_employment_id', Employment::class, 'employee_no'),
        ];
    }

    /**
     * @param  Collection<int, Assignment>  $assignments
     * @param  array<string, array<int, string>>  $names
     * @return array{date: string, order: int, title: string, detail: string, who: string, request: ?int}
     */
    private function eventRow(EmploymentEvent $event, Collection $assignments, array $names): array
    {
        $title = match ($event->type) {
            EventType::HIRE => 'Hire',
            EventType::REJOIN => 'Rejoin',
            EventType::CONFIRMATION => 'Confirmation',
            EventType::ASSIGNMENT_CHANGE => 'Assignment change',
            EventType::TYPE_CHANGE => 'Type change',
            EventType::SUSPENSION => 'Suspension',
            EventType::REINSTATEMENT => 'Reinstatement',
            EventType::EXIT => 'Exit',
            EventType::SISTER_COMPANY_TRANSFER_OUT => 'Transfer to sister company',
            EventType::SISTER_COMPANY_TRANSFER_IN => 'Transfer from sister company',
        };

        $detail = [];

        if ($event->type === EventType::ASSIGNMENT_CHANGE) {
            $from = (array) ($event->details['from'] ?? []);
            $to = (array) ($event->details['to'] ?? []);
            foreach (self::FIELD_LABELS as $field => $label) {
                if (! array_key_exists($field, $to)) {
                    continue;
                }
                $old = isset($from[$field]) ? ($names[$field][(int) $from[$field]] ?? '#'.$from[$field]) : '—';
                $new = isset($to[$field]) ? ($names[$field][(int) $to[$field]] ?? '#'.$to[$field]) : '—';
                $detail[] = "{$label} {$old} → {$new}";
            }

            $reason = $assignments->first(fn (Assignment $a) => $a->effective_from->toDateString() === $event->event_date->toDateString())?->change_reason;
            if ($reason) {
                $detail[] = "reason: {$reason}";
            }
        }

        if ($event->type === EventType::SUSPENSION && ! empty($event->details['remarks'])) {
            $detail[] = 'remarks: '.$event->details['remarks'];
        }

        if ($event->type === EventType::EXIT && $this->employment->exit_reason !== null) {
            $detail[] = 'reason: '.strtolower(str_replace('_', ' ', $this->employment->exit_reason->value));
        }

        if (in_array($event->type, [EventType::SISTER_COMPANY_TRANSFER_OUT, EventType::SISTER_COMPANY_TRANSFER_IN, EventType::REJOIN], true) && $event->relatedEmployment) {
            $detail[] = 'employee no. '.$event->relatedEmployment->employee_no;
        }

        return [
            'date' => $event->event_date->toDateString(),
            'order' => (int) $event->id,
            'title' => $title,
            'detail' => implode(', ', $detail),
            'who' => $event->creator?->name ?? $event->approvalRequest?->requester?->name ?? '—',
            'request' => $event->approval_request_id,
        ];
    }

    /**
     * Assignments with no matching event on their start date (for example imported data).
     *
     * @param  Collection<int, EmploymentEvent>  $events
     * @param  Collection<int, Assignment>  $assignments
     * @return Collection<int, array{date: string, order: int, title: string, detail: string, who: string, request: ?int}>
     */
    private function assignmentsWithoutEvents(Collection $events, Collection $assignments): Collection
    {
        $eventDates = $events
            ->filter(fn (EmploymentEvent $e) => in_array($e->type, [EventType::HIRE, EventType::REJOIN, EventType::ASSIGNMENT_CHANGE, EventType::SISTER_COMPANY_TRANSFER_IN], true))
            ->map(fn (EmploymentEvent $e) => $e->event_date->toDateString())
            ->all();

        return $assignments
            ->reject(fn (Assignment $a) => in_array($a->effective_from->toDateString(), $eventDates, true))
            ->map(fn (Assignment $a) => [
                'date' => $a->effective_from->toDateString(),
                'order' => 0,
                'title' => 'Assignment',
                'detail' => trim(($a->job?->name ?? '').' · '.($a->grade?->name ?? '').($a->change_reason ? ', reason: '.$a->change_reason : '')),
                'who' => '—',
                'request' => null,
            ])
            ->values();
    }
}
