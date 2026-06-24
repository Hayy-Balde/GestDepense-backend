<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SavingStatus;
use App\Enums\SavingTransactionType;
use App\Traits\HasUuid;
use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Saving extends Model
{
    use HasFactory, HasUuid, BelongsToUser;

    protected $guarded = ['id'];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'current_amount' => 'decimal:2',
        'auto_save_amount' => 'decimal:2',
        'deadline' => 'date',
        'status' => SavingStatus::class,
    ];

    protected $attributes = [
        'current_amount' => 0,
        'status' => 'active',
    ];

    protected $appends = ['progress', 'remaining'];

    public function transactions(): HasMany
    {
        return $this->hasMany(SavingTransaction::class)->orderByDesc('date');
    }

    public function getProgressAttribute(): float
    {
        if ((float) $this->target_amount <= 0) return 0;
        return round(((float) $this->current_amount / (float) $this->target_amount) * 100, 1);
    }

    public function getRemainingAttribute(): float
    {
        return max(0, (float) $this->target_amount - (float) $this->current_amount);
    }

    public function deposit(float $amount, ?string $note = null): SavingTransaction
    {
        $this->increment('current_amount', $amount);

        if ((float) $this->current_amount >= (float) $this->target_amount) {
            $this->update(['status' => SavingStatus::COMPLETED]);
        }

        return $this->transactions()->create([
            'type' => SavingTransactionType::DEPOSIT,
            'amount' => $amount,
            'date' => now(),
            'note' => $note,
        ]);
    }

    public function withdraw(float $amount, ?string $note = null): SavingTransaction
    {
        $this->decrement('current_amount', min($amount, (float) $this->current_amount));

        if ($this->status === SavingStatus::COMPLETED) {
            $this->update(['status' => SavingStatus::ACTIVE]);
        }

        return $this->transactions()->create([
            'type' => SavingTransactionType::WITHDRAWAL,
            'amount' => $amount,
            'date' => now(),
            'note' => $note,
        ]);
    }

    public function scopeActive($query)
    {
        return $query->where('status', SavingStatus::ACTIVE);
    }
}
