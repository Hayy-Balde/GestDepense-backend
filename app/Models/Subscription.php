<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingCycle;
use App\Traits\HasUuid;
use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasUuid, BelongsToUser;

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'billing_cycle' => BillingCycle::class,
        'next_billing_date' => 'date',
        'is_active' => 'boolean',
        'reminder_days_before' => 'integer',
    ];

    protected $attributes = [
        'is_active' => true,
        'reminder_days_before' => 3,
        'currency_code' => 'GNF',
    ];

    protected $appends = ['annual_cost'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function getAnnualCostAttribute(): float
    {
        return (float) $this->amount * $this->billing_cycle->annualMultiplier();
    }

    public function isDueSoon(int $days = 3): bool
    {
        return $this->is_active && $this->next_billing_date?->diffInDays(now()) <= $days;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeUpcoming($query, int $days = 7)
    {
        return $query->active()
            ->where('next_billing_date', '<=', now()->addDays($days))
            ->where('next_billing_date', '>=', now())
            ->orderBy('next_billing_date');
    }
}
