<?php

namespace App\Http\Traits;

trait TracksActions
{
    protected static function bootTracksActions(): void
    {
        static::deleting(function ($model) {
            $model->deleted_by = auth()->id();
            $model->saveQuietly();
        });

        static::restoring(function ($model) {
            $model->deleted_by = null;
            $model->saveQuietly();
        });

        static::created(function ($model) {
            $model->created_by = auth()->id();
            $model->saveQuietly();
        });

        static::updating(function ($model) {
            $model->updated_by = auth()->id();
            $model->saveQuietly();
        });
    }

}
