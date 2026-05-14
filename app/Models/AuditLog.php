<?php

namespace App\Models;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'changes' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record an audit entry. Safe to call from anywhere — never throws.
     */
    public static function record(string $action, ?Model $subject = null, array $changes = null, ?array $context = null): ?self
    {
        try {
            $user = auth()->user();
            $request = request();

            return self::create([
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? ($context['actor_name'] ?? 'system'),
                'action' => $action,
                'subject_type' => $subject ? get_class($subject) : ($context['subject_type'] ?? null),
                'subject_id' => $subject?->getKey() ? (string) $subject->getKey() : ($context['subject_id'] ?? null),
                'subject_label' => $subject ? self::deriveLabel($subject) : ($context['subject_label'] ?? null),
                'changes' => $changes ?: null,
                'ip_address' => $request?->ip(),
                'user_agent' => substr((string) ($request?->userAgent() ?? ''), 0, 512) ?: null,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('AuditLog::record failed', ['error' => $e->getMessage(), 'action' => $action]);
            return null;
        }
    }

    private static function deriveLabel(Model $subject): ?string
    {
        foreach (['matricule', 'name', 'title', 'reference', 'email'] as $attr) {
            $value = $subject->getAttribute($attr);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }
        return class_basename($subject) . '#' . $subject->getKey();
    }
}
