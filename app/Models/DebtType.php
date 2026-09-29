<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DebtType extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $primaryKey = 'value';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
