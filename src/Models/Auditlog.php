<?php

namespace Optima\DepotStock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    /**
     * Tiny helper to record an event.
     *
     * @param  string          $action        e.g. "user.created"
     * @param  object|null     $subject       Eloquent model or anything with id
     * @param  array           $metadata      Extra context
     */
    public static function record(string $action, $subject = null, array $metadata = []): self
    {
        $userId = Auth::id();

        $attrs = [
            'user_id'      => $userId,
            'action'       => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id'   => $subject?->id ?? null,
            'metadata'     => $metadata,
        ];

        // Optional: generate a simple description
        if (! isset($metadata['description'])) {
            $attrs['description'] = static::defaultDescription($action, $subject, $metadata);
        } else {
            $attrs['description'] = $metadata['description'];
            unset($attrs['metadata']['description']);
        }

        return static::create($attrs);
    }

    protected static function defaultDescription(string $action, $subject, array $meta): string
    {
        $subjectLabel = method_exists($subject, 'getAttribute')
            ? ($subject->name ?? $subject->email ?? ('#'.$subject->id))
            : null;

        return match ($action) {
            'user.created' =>
                "User created: {$subjectLabel}",
            'user.roles_updated' =>
                "User roles updated: {$subjectLabel}",
            'user.password_reset' =>
                "Password reset for user: {$subjectLabel}",
            'pool.transfer' =>
                "Depot pool transfer executed",
            'pool.sell' =>
                "Depot pool sell executed",
            default =>
                $action . ($subjectLabel ? " ({$subjectLabel})" : ''),
        };
    }
}