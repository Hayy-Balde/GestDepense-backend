<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Category extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    public function user() { return $this->belongsTo(User::class); }
    public function subCategories() { return $this->hasMany(SubCategory::class); }
    public function expenses() { return $this->hasMany(Expense::class); }
    public function incomes() { return $this->hasMany(Income::class); }
}
