<?php

namespace Optima\DepotStock\Support;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Arr;
use Optima\DepotStock\Models\Role;

trait HasRoles
{
    /**
     * Relationship: user ↔ roles.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withTimestamps();
    }

    /**
     * Assign one or more roles by name or Role model.
     *
     * $user->assignRole('admin');
     * $user->assignRole(['operations', 'accountant']);
     */
    public function assignRole($roles): self
    {
        $roleIds = collect(Arr::wrap($roles))
            ->map(function ($role) {
                if ($role instanceof Role) {
                    return $role->id;
                }

                return Role::where('name', $role)->value('id');
            })
            ->filter()
            ->all();

        if (! empty($roleIds)) {
            $this->roles()->syncWithoutDetaching($roleIds);
            $this->load('roles');
        }

        return $this;
    }

    /**
     * Replace existing roles with the given set.
     *
     * $user->syncRoles(['admin','operations']);
     */
    public function syncRoles($roles): self
    {
        $roleIds = collect(Arr::wrap($roles))
            ->map(function ($role) {
                if ($role instanceof Role) {
                    return $role->id;
                }

                return Role::where('name', $role)->value('id');
            })
            ->filter()
            ->all();

        $this->roles()->sync($roleIds);
        $this->load('roles');

        return $this;
    }

    /**
     * Remove a role.
     *
     * $user->removeRole('viewer');
     */
    public function removeRole($role): self
    {
        if ($role instanceof Role) {
            $roleId = $role->id;
        } else {
            $roleId = Role::where('name', $role)->value('id');
        }

        if ($roleId) {
            $this->roles()->detach($roleId);
            $this->load('roles');
        }

        return $this;
    }

    /**
     * Check if user has a given role (or any of a set).
     *
     * $user->hasRole('admin')
     * $user->hasRole(['admin','operations'])
     */
    public function hasRole($roles): bool
    {
        $names = $this->roles->pluck('name')->all();

        foreach (Arr::wrap($roles) as $role) {
            if (in_array($role, $names, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convenience alias.
     */
    public function hasAnyRole($roles): bool
    {
        return $this->hasRole($roles);
    }

    /**
     * Check if user has all given roles.
     */
    public function hasAllRoles($roles): bool
    {
        $names = $this->roles->pluck('name')->all();

        foreach (Arr::wrap($roles) as $role) {
            if (! in_array($role, $names, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Quick helper for admin checks.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Expose role names as a simple array (optional sugar).
     * $user->role_names → ['admin','operations']
     */
    public function getRoleNamesAttribute(): array
    {
        return $this->roles->pluck('name')->all();
    }
}