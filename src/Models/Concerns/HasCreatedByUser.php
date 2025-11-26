<?php

namespace Optima\DepotStock\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait HasCreatedByUser
{
    public static function bootHasCreatedByUser(): void
    {
        static::creating(function (Model $model) {
            // Only set if empty and we have an authenticated user
            if (
                !$model->getAttribute('created_by_user_id')
                && Auth::check()
            ) {
                $model->setAttribute('created_by_user_id', Auth::id());
            }
        });
    }

    /**
     * User who created this record.
     */
    public function createdBy()
    {
        // Respect app auth user model if customised
        $userModel = config('auth.providers.users.model') ?? \App\Models\User::class;

        return $this->belongsTo($userModel, 'created_by_user_id');
    }
}