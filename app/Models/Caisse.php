<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Caisse extends Model
{
    use HasFactory, HasUuid, BelongsToUser;

    protected $guarded = ['id'];

    protected $casts = [
        'budget_amount' => 'decimal:2',
        'spent_amount' => 'decimal:2',
    ];

    protected $attributes = [
        'spent_amount' => 0,
    ];

    protected $appends = ['remaining', 'percentage_used'];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function getRemainingAttribute(): float
    {
        return (float) $this->budget_amount - (float) $this->spent_amount;
    }

    public function getPercentageUsedAttribute(): float
    {
        if ((float) $this->budget_amount <= 0) return 0;
        return round(((float) $this->spent_amount / (float) $this->budget_amount) * 100, 1);
    }

    public function isOverBudget(): bool
    {
        return (float) $this->spent_amount > (float) $this->budget_amount;
    }

    public function addSpending(float $amount): void
    {
        $this->increment('spent_amount', $amount);
    }

    public function removeSpending(float $amount): void
    {
        $this->decrement('spent_amount', $amount);
    }
}
