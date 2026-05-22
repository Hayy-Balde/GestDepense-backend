<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SubCategory extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    public function category() { return $this->belongsTo(Category::class); }
    public function expenses() { return $this->hasMany(Expense::class); }
}
