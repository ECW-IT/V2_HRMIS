<?php

use Modules\CoreHr\Readiness\Checks\Hr001NoAssignment;
use Modules\CoreHr\Readiness\Checks\Hr002InactiveAssignmentTargets;
use Modules\CoreHr\Readiness\Checks\Hr003EpfMissing;
use Modules\CoreHr\Readiness\Checks\Hr004NoBankAccount;
use Modules\CoreHr\Readiness\Checks\Hr005NoEnrolment;
use Modules\CoreHr\Readiness\Checks\Hr006ManagerLeft;
use Modules\CoreHr\Readiness\Checks\Hr007NicMismatch;
use Modules\CoreHr\Readiness\Checks\Hr008ProbationOverdue;
use Modules\CoreHr\Readiness\Checks\Hr009MandatoryDocumentMissing;
use Modules\CoreHr\Readiness\Checks\Hr010DocumentExpiring;
use Modules\CoreHr\Readiness\Checks\Hr011NoMobile;
use Modules\CoreHr\Readiness\Checks\Hr012NoTimeProfile;

return [
    'checks' => [
        Hr001NoAssignment::class,
        Hr002InactiveAssignmentTargets::class,
        Hr003EpfMissing::class,
        Hr004NoBankAccount::class,
        Hr005NoEnrolment::class,
        Hr006ManagerLeft::class,
        Hr007NicMismatch::class,
        Hr008ProbationOverdue::class,
        Hr009MandatoryDocumentMissing::class,
        Hr010DocumentExpiring::class,
        Hr011NoMobile::class,
        Hr012NoTimeProfile::class,
    ],
];
