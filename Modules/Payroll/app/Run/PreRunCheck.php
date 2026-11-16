<?php

namespace Modules\Payroll\Run;

final readonly class PreRunCheck
{
    /**
     * @param  list<int>  $employmentIds
     */
    public function __construct(
        public string $code,
        public string $title,
        public bool $blocking,
        public int $count,
        public bool $ok,
        public array $employmentIds = [],
    ) {}
}
