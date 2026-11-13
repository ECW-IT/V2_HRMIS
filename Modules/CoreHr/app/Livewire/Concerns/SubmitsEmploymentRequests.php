<?php

namespace Modules\CoreHr\Livewire\Concerns;

use App\Exceptions\Domain\DomainException;
use Modules\CoreHr\Services\EmploymentRequestService;

/**
 * For components that hold `public Employment $employment` and send changes
 * through EmploymentRequestService. Adds no business rules.
 */
trait SubmitsEmploymentRequests
{
    public string $message = '';

    public string $errorMessage = '';

    /** True when the last request was applied at once (not sent for approval). Valid within one request only. */
    protected bool $appliedAtOnce = false;

    /** Dialogs show their result on the profile; tabs show it themselves. */
    protected bool $flashOnParent = true;

    /** @param  array<string, mixed>  $data */
    protected function submitRequest(string $operation, array $data): bool
    {
        $this->message = '';
        $this->errorMessage = '';
        $this->appliedAtOnce = false;

        try {
            $request = app(EmploymentRequestService::class)->request(auth()->user(), $operation, $this->employment, $data);
        } catch (DomainException $e) {
            $this->errorMessage = $e->getMessage();

            return false;
        }

        $this->appliedAtOnce = $request === null;
        $this->message = $request === null
            ? 'Saved.'
            : "Sent for approval (request #{$request->id}).";

        $this->dispatch('employment-updated', message: $this->flashOnParent ? $this->message : '');

        return true;
    }
}
