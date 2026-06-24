<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CategoryType;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory, HasUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'type' => CategoryType::class,
        'is_system' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function subCategories(): HasMany
    {
        return $this->hasMany(SubCategory::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function budgetCategories(): HasMany
    {
        return $this->hasMany(BudgetCategory::class);
    }

    public function scopeExpenseType($query)
    {
        return $query->where('type', CategoryType::EXPENSE);
    }

    public function scopeIncomeType($query)
    {
        return $query->where('type', CategoryType::INCOME);
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    public function scopeForUser($query, ?string $userId = null)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('is_system', true)
              ->orWhere('user_id', $userId ?? auth()->id());
        });
    }
}
