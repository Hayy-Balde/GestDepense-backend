<?php
namespace App\Traits;
trait Filterable {
    public function scopeFilter($query, array $filters) {
        // Apply dynamic filters
        return $query;
    }
}