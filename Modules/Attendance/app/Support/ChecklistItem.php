<?php

namespace Modules\Attendance\Support;

final readonly class ChecklistItem
{
    public function __construct(
        public string $code,
        public string $title,
        public bool $blocking,
        public int $count,
        public bool $ok,
    ) {}
}
