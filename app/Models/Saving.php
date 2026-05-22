<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Saving extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    public function user() { return $this->belongsTo(User::class); }
    public function transactions() { return $this->hasMany(SavingTransaction::class); }
}
