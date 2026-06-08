<?php

namespace App\Http\Concerns;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * Single source of truth for which roles the current user is allowed to
 * assign. Only a Super Admin can hand out the "Super Admin" role; everyone
 * else is restricted to the remaining roles. Shared by controllers and form
 * requests so the privilege-escalation guard cannot drift between them.
 */
trait AssignableRoles
{
    protected function assignableRoleQuery()
    {
        $query = Role::query();

        if (! auth()->user()?->hasRole('Super Admin')) {
            $query->where('name', '!=', 'Super Admin');
        }

        return $query;
    }

    protected function assignableRoleNames(): Collection
    {
        return $this->assignableRoleQuery()->orderBy('name')->pluck('name');
    }

    protected function assignableRoleIds(): Collection
    {
        return $this->assignableRoleQuery()->pluck('id');
    }
}
