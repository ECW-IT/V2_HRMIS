<?php

namespace Modules\LegacyImport\Support;

final class ImportIssue
{
    public function __construct(
        public readonly string $code,
        public readonly string $severity,
        public readonly string $message,
        public readonly ?int $legacyHrmId = null,
    ) {}
}
