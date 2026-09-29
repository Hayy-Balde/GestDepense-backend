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
        'date' => 'date',
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
    public const TYPE_DEBT_LEND = 'debt_lend';
    public const TYPE_DEBT_BORROW = 'debt_borrow';
    public const TYPE_DEBT_REPAYMENT = 'debt_repayment';
    public const TYPE_DEBT_REVERSAL = 'debt_reversal';

    public const DEBT_TYPES = [
        self::TYPE_DEBT_LEND,
        self::TYPE_DEBT_BORROW,
        self::TYPE_DEBT_REPAYMENT,
        self::TYPE_DEBT_REVERSAL,
    ];

    public function scopeForPeriod($query, $start, $end)
    {
        return $query->whereBetween('date', [$start, $end]);
    }

    public function scopeDebts($query)
    {
        return $query->whereIn('type', self::DEBT_TYPES);
    }

    public function source(): MorphTo
    {
        return $this->morphTo('from', 'from_type', 'from_id');
    }

    public function destination(): MorphTo
    {
        return $this->morphTo('to', 'to_type', 'to_id');
    }
}