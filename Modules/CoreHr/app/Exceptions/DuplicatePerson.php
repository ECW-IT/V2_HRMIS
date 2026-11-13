<?php

namespace Modules\CoreHr\Exceptions;

use App\Exceptions\Domain\DomainException;

final class DuplicatePerson extends DomainException
{
    public function __construct(
        public readonly int $existingPersonId,
        string $message = 'A person with this identity already exists.',
    ) {
        parent::__construct($message);
    }
}
