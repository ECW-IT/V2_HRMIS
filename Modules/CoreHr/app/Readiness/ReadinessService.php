<?php

namespace Modules\CoreHr\Readiness;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\Company;

final class ReadinessService
{
    /** @return Collection<int, ReadinessIssue> */
    public function run(Company $company, \DateTimeInterface|string $asOf): Collection
    {
        $day = $this->asOf($asOf);
        $issues = collect();

        foreach ($this->checks() as $check) {
            foreach ($check->failing($company, $day) as $employmentId) {
                $issues->push(new ReadinessIssue(
                    $check->code(),
                    $check->title(),
                    $check->severity(),
                    (int) $employmentId,
                ));
            }
        }

        return $issues->values();
    }

    /** @return Collection<int, ReadinessIssue> */
    public function forEmployment(Employment $e, \DateTimeInterface|string $asOf): Collection
    {
        $day = $this->asOf($asOf);
        $company = Company::query()->findOrFail($e->company_id);
        $issues = collect();

        foreach ($this->checks() as $check) {
            if (in_array((int) $e->id, $check->failing($company, $day), true)) {
                $issues->push(new ReadinessIssue(
                    $check->code(),
                    $check->title(),
                    $check->severity(),
                    (int) $e->id,
                ));
            }
        }

        return $issues->values();
    }

    /** [code => ['title' =>, 'severity' =>, 'count' =>]] for every configured check, including zero counts. */
    public function summary(Company $company, \DateTimeInterface|string $asOf): array
    {
        $day = $this->asOf($asOf);
        $summary = [];

        foreach ($this->checks() as $check) {
            $summary[$check->code()] = [
                'title' => $check->title(),
                'severity' => $check->severity(),
                'count' => count($check->failing($company, $day)),
            ];
        }

        return $summary;
    }

    /** @return list<ReadinessCheck> */
    private function checks(): array
    {
        /** @var list<class-string<ReadinessCheck>> $classes */
        $classes = config('corehr.readiness.checks', []);

        return array_map(fn (string $class) => app($class), $classes);
    }

    private function asOf(\DateTimeInterface|string $asOf): CarbonImmutable
    {
        if ($asOf instanceof CarbonImmutable) {
            return $asOf->startOfDay();
        }

        if ($asOf instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($asOf)->startOfDay();
        }

        return CarbonImmutable::parse($asOf, config('app.timezone'))->startOfDay();
    }
}
