<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SavingTransactionType;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavingTransaction extends Model
{
    use HasUuid;

    protected $table = 'saving_transactions';

    protected $guarded = ['id'];

    protected $casts = [
        'type' => SavingTransactionType::class,
        'amount' => 'decimal:2',
        'date' => 'date',
    ];

    public function savingGoal(): BelongsTo
    {
        return $this->belongsTo(Saving::class);
    }
}
