<?php

namespace App\Policies;

use App\Models\LeadSource;
use App\Models\User;

class LeadSourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('leads.view');
    }

    public function view(User $user, LeadSource $source): bool
    {
        return $user->hasPermission('leads.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('leads.import');
    }

    public function import(User $user): bool
    {
        return $user->hasPermission('leads.import');
    }

    public function delete(User $user, LeadSource $source): bool
    {
        return $user->hasPermission('leads.delete');
    }

    public function purge(User $user): bool
    {
        return $user->hasPermission('leads.delete');
    }

    public function cancel(User $user, LeadSource $source): bool
    {
        return $user->hasPermission('automation.cancel');
    }

    public function reprocess(User $user, LeadSource $source): bool
    {
        return $user->hasPermission('automation.reprocess');
    }

    public function normalize(User $user, LeadSource $source): bool
    {
        return $user->hasPermission('leads.normalize');
    }

    public function merge(User $user): bool
    {
        return $user->hasPermission('leads.merge');
    }
}
