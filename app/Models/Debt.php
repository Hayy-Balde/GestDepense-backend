<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Debt extends Model
{
    use HasFactory, HasUuid, BelongsToUser;

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
    ];

    protected $appends = ['progress', 'is_overdue'];

    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
    public function caisse() { return $this->belongsTo(Caisse::class); }
    public function payments(): HasMany { return $this->hasMany(DebtPayment::class); }

    public function getProgressAttribute(): int
    {
        $amount = (float) $this->amount;

        if ($amount <= 0) {
            return 0;
        }

        return (int) round(((($amount - (float) $this->remaining_amount) / $amount) * 100));
    }

    public function getIsOverdueAttribute(): bool
    {
        if ($this->due_date === null || $this->status === 'paid') {
            return false;
        }

        return \Illuminate\Support\Carbon::parse($this->due_date)->lt(now()->startOfDay());
    }
}
