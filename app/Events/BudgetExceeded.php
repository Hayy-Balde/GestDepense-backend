<?php
namespace App\Events;
use App\Models\Budget;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BudgetExceeded {
    use Dispatchable, SerializesModels;
    public $budget;
    public function __construct(Budget $budget) {
        $this->budget = $budget;
    }
}