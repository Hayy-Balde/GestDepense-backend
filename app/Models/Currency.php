<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Currency extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $primaryKey = "code";
    public $incrementing = false;
    protected $keyType = "string";
}
