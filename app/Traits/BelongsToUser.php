<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToUser
{
    protected static function bootBelongsToUser(): void
    {
        static::creating(function ($model) {
            if (empty($model->user_id) && auth()->check()) {
                $model->user_id = auth()->id();
            }
        });

        // L'appartenance à un tenant n'est jamais modifiable après la création.
        // Sans ce garde-fou, un endpoint d'update dont la validation aurait été
        // oubliée permettrait de déplacer une ligne vers un autre utilisateur
        // (écriture inter-comptes sur des données financières).
        static::updating(function ($model) {
            if ($model->isDirty('user_id')) {
                $model->setAttribute('user_id', $model->getOriginal('user_id'));
            }
        });

        static::addGlobalScope('user', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where($builder->getModel()->getTable() . '.user_id', auth()->id());
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->withoutGlobalScope('user')->where('user_id', $userId);
    }
}