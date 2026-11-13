<?php

namespace Modules\CoreHr\Readiness;

final readonly class ReadinessIssue
{
    public function __construct(
        public string $code,
        public string $title,
        public Severity $severity,
        public int $employmentId,
    ) {}
}
