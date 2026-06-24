<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait Filterable
{
    protected array $searchable = [];
    protected array $filterable = [];
    protected string $defaultSort = 'created_at';

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        foreach ($filters as $field => $value) {
            if (is_null($value) || $value === '') continue;

            match (true) {
                $field === 'search' => $query->where(function ($q) use ($value) {
                    foreach ($this->searchable as $searchField) {
                        $q->orWhere($searchField, 'ILIKE', "%{$value}%");
                    }
                }),
                $field === 'date_from' => $query->where('date', '>=', $value),
                $field === 'date_to' => $query->where('date', '<=', $value),
                $field === 'amount_min' => $query->where('amount', '>=', $value),
                $field === 'amount_max' => $query->where('amount', '<=', $value),
                $field === 'month' => $query->whereMonth('date', $value),
                $field === 'year' => $query->whereYear('date', $value),
                str_ends_with($field, '_id') => $query->where($field, $value),
                in_array($field, $this->filterable) => $query->where($field, $value),
                default => null,
            };
        }

        return $query;
    }

    public function scopeSortBy(Builder $query, ?string $field = null, string $direction = 'desc'): Builder
    {
        $field = $field ?? $this->defaultSort;
        $direction = in_array(strtolower($direction), ['asc', 'desc']) ? $direction : 'desc';
        return $query->orderBy($field, $direction);
    }
}