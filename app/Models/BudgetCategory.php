<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetCategory extends Model
{
    use HasUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'spent_amount' => 'decimal:2',
    ];

    protected $attributes = [
        'spent_amount' => 0,
    ];

    protected $appends = ['remaining', 'percentage_used'];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function getRemainingAttribute(): float
    {
        return (float) $this->allocated_amount - (float) $this->spent_amount;
    }

    public function getPercentageUsedAttribute(): float
    {
        if ((float) $this->allocated_amount <= 0) return 0;
        return round(((float) $this->spent_amount / (float) $this->allocated_amount) * 100, 1);
    }
}
