<?php

namespace Modules\Attendance\Support;

final readonly class IngestResult
{
    public function __construct(
        public int $received,
        public int $inserted,
        public int $duplicates,
        public int $unresolved,
        public int $rejected,
    ) {}

    public function toArray(): array
    {
        return [
            'received' => $this->received,
            'inserted' => $this->inserted,
            'duplicates' => $this->duplicates,
            'unresolved' => $this->unresolved,
            'rejected' => $this->rejected,
        ];
    }
}
