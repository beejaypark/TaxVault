<?php

namespace App\Domain\Billing;

use App\Models\User;
use Carbon\CarbonImmutable;

final class DocumentUploadAccess
{
    public function allows(User $user): bool
    {
        if ($user->billing_exempt || $user->isPlatformAdmin()) {
            return true;
        }

        if ($user->stripe_subscription_status === 'active') {
            return true;
        }

        if ($user->stripe_subscription_status === 'past_due') {
            return $user->trial_ends_at === null || $user->trial_ends_at->isFuture();
        }

        return $user->stripe_subscription_status === 'trialing'
            && $user->trial_ends_at !== null
            && $user->trial_ends_at->isFuture();
    }
}
