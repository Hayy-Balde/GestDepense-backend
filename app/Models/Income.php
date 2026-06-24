<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IncomeSourceType;
use App\Traits\HasUuid;
use App\Traits\BelongsToUser;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Income extends Model
{
    use HasFactory, HasUuid, BelongsToUser, Filterable;

    protected $guarded = ['id'];

    protected array $searchable = ['title', 'description'];
    protected array $filterable = ['source_type', 'is_recurring'];
    protected string $defaultSort = 'date';

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'source_type' => IncomeSourceType::class,
        'is_recurring' => 'boolean',
    ];

    protected $attributes = [
        'is_recurring' => false,
        'currency_code' => 'GNF',
        'source_type' => 'other',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeForMonth($query, int $month, int $year)
    {
        return $query->whereMonth('date', $month)->whereYear('date', $year);
    }

    public function scopeOfSource($query, IncomeSourceType $type)
    {
        return $query->where('source_type', $type);
    }
}
