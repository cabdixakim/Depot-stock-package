<?php

namespace Optima\DepotStock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $table = 'roles';

    protected $fillable = [
        'name',
    ];

    /**
     * Users that belong to this role.
     */
    public function users(): BelongsToMany
    {
        // Uses the main app User model from auth config
        $userModel = config('auth.providers.users.model', \App\Models\User::class);

        return $this->belongsToMany($userModel)
            ->withTimestamps();
    }
}