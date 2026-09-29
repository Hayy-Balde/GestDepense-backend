<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'amount' => 'float',
        'date' => 'date',
    ];

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
    public function caisse(): BelongsTo { return $this->belongsTo(Caisse::class); }
}