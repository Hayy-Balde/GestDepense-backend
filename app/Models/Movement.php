<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Movement extends Model
{
    use HasUuid, BelongsToUser;

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public const TYPE_APPORT = 'apport';
    public const TYPE_REGULATION = 'regulation';
    public const TYPE_TRANSFER = 'transfer';
    public const TYPE_EXPENSE = 'expense';
    public const TYPE_INCOME = 'income';
    public const TYPE_CAISSE_FUND = 'caisse_fund';
    public const TYPE_CAISSE_REFUND = 'caisse_refund';
    public const TYPE_CAISSE_CLOSE = 'caisse_close';
    public const TYPE_OUT = 'out';

    public function source(): MorphTo
    {
        return $this->morphTo('from', 'from_type', 'from_id');
    }

    public function destination(): MorphTo
    {
        return $this->morphTo('to', 'to_type', 'to_id');
    }
}