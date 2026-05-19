<?php

namespace App\Http\Traits;

use App\Models\AuditLog;

trait TracksActions
{
    protected static function bootTracksActions(): void
    {
        static::deleting(function ($model) {
            $model->deleted_by = auth()->id();
            $model->saveQuietly();
        });

        static::deleted(function ($model) {
            AuditLog::record('deleted', $model);
        });

        // restoring/restored only exist when the model uses SoftDeletes —
        // wire them only when the trait is present, otherwise the static call
        // hits an undefined method on plain Eloquent models.
        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restoring(function ($model) {
                $model->deleted_by = null;
                $model->saveQuietly();
            });

            static::restored(function ($model) {
                AuditLog::record('restored', $model);
            });
        }

        static::created(function ($model) {
            $model->created_by = auth()->id();
            $model->saveQuietly();
            AuditLog::record('created', $model);
        });

        static::updating(function ($model) {
            $model->updated_by = auth()->id();
        });

        static::updated(function ($model) {
            $changed = collect($model->getChanges())
                ->except(['updated_at', 'updated_by'])
                ->all();

            if (empty($changed)) {
                return;
            }

            $original = collect($changed)
                ->mapWithKeys(fn ($_, $key) => [$key => $model->getOriginal($key)])
                ->all();

            AuditLog::record('updated', $model, [
                'before' => $original,
                'after' => $changed,
            ]);
        });
    }
}
