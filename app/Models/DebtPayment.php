<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DebtPayment extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'debtpayments';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'float',
        'date' => 'date',
    ];

    public function debt() { return $this->belongsTo(Debt::class); }

    public function account() { return $this->belongsTo(Account::class); }
    public function caisse() { return $this->belongsTo(Caisse::class); }
}
