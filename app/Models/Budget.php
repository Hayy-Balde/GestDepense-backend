<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends Model
{
    use HasUuid, BelongsToUser;

    protected $guarded = ['id'];

    protected $casts = [
        'total_budget' => 'decimal:2',
        'month' => 'integer',
        'year' => 'integer',
    ];

    public function categories(): HasMany
    {
        return $this->hasMany(BudgetCategory::class);
    }

    public function getTotalSpentAttribute(): float
    {
        return (float) $this->categories->sum('spent_amount');
    }

    public function getRemainingAttribute(): float
    {
        return (float) $this->total_budget - $this->total_spent;
    }

    public function getSurplusAttribute(): float
    {
        return $this->remaining;
    }

    public function isOverBudget(): bool
    {
        return $this->total_spent > (float) $this->total_budget;
    }

    public function scopeForMonth($query, int $month, int $year)
    {
        return $query->where('month', $month)->where('year', $year);
    }
}
