<?php

namespace App\Policies;

use App\Models\Bill;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('payments.view');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->sameOrganization($user, $payment)
            && $user->hasPermission('payments.view')
            && $this->hasProjectAccess($user, $payment->bill->project);
    }

    /**
     * Payments can only be recorded against a CERTIFIED (or already
     * partially-paid) bill — never a draft/submitted one, since the
     * payable amount isn't finalized yet.
     */
    public function createForBill(User $user, Bill $bill): bool
    {
        if (! $bill->isCertified()) {
            return false;
        }

        return ! $user->is_super_admin
            && $user->organization_id === $bill->organization_id
            && $user->hasPermission('payments.create')
            && $this->hasProjectAccess($user, $bill->project);
    }

    private function hasProjectAccess(User $user, $project): bool
    {
        return $user->hasOrgWideVisibility() || $project->isUserAssigned($user->id);
    }

    private function sameOrganization(User $user, Payment $payment): bool
    {
        return ! $user->is_super_admin && $user->organization_id === $payment->organization_id;
    }
}
