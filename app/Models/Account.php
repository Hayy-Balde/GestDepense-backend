<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountType;
use App\Traits\HasUuid;
use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory, HasUuid, BelongsToUser;

    protected $guarded = ['id'];

    protected $casts = [
        'type' => AccountType::class,
        'balance' => 'decimal:2',
        'credit_limit' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'balance' => 0,
        'is_active' => true,
        'currency_code' => 'GNF',
    ];

    /* Relations */

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /* Scopes */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, AccountType $type)
    {
        return $query->where('type', $type);
    }

    /* Helpers */

    public function adjustBalance(float $amount): void
    {
        $this->increment('balance', $amount);
    }

    public function getFormattedBalanceAttribute(): string
    {
        return number_format((float) $this->balance, 2) . ' ' . $this->currency_code;
    }
}
