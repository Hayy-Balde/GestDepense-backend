<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory, HasUuid, BelongsToUser;

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
    ];

    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
    public function caisse() { return $this->belongsTo(Caisse::class); }
    public function payments(): HasMany { return $this->hasMany(InvoicePayment::class); }
}
