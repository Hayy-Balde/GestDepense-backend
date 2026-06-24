<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Traits\HasUuid;
use App\Traits\BelongsToUser;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Expense extends Model
{
    use HasFactory, HasUuid, BelongsToUser, Filterable;

    protected $guarded = ['id'];

    protected array $searchable = ['title', 'description', 'notes'];
    protected array $filterable = ['payment_method', 'status', 'is_recurring'];
    protected string $defaultSort = 'date';

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'payment_method' => PaymentMethod::class,
        'status' => TransactionStatus::class,
        'is_recurring' => 'boolean',
    ];

    protected $attributes = [
        'status' => 'completed',
        'is_recurring' => false,
        'currency_code' => 'GNF',
        'payment_method' => 'cash',
    ];

    /* Relations */

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(SubCategory::class);
    }

    public function caisse(): BelongsTo
    {
        return $this->belongsTo(Caisse::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'expense_tag');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /* Scopes */

    public function scopeCompleted($query)
    {
        return $query->where('status', TransactionStatus::COMPLETED);
    }

    public function scopeForMonth($query, int $month, int $year)
    {
        return $query->whereMonth('date', $month)->whereYear('date', $year);
    }

    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }

    public function scopeBetweenDates($query, string $from, string $to)
    {
        return $query->whereBetween('date', [$from, $to]);
    }
}
