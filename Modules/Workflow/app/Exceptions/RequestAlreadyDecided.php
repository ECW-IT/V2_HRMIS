<?php

namespace Modules\Workflow\Exceptions;

use App\Exceptions\Domain\DomainException;

final class RequestAlreadyDecided extends DomainException {}
