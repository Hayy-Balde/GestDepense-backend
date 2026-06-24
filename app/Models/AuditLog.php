<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AuditLog extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    public function user() { return $this->belongsTo(User::class); }
    public function auditable() { return $this->morphTo(); }
}
