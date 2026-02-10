<?php

namespace App\Policies;

use App\Models\LeadNormalized;
use App\Models\User;

class LeadNormalizedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('leads.view');
    }

    public function view(User $user, LeadNormalized $lead): bool
    {
        return $user->hasPermission('leads.view');
    }
}
