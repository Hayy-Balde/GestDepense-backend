<?php
namespace App\Events;
use App\Models\Expense;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExpenseCreated {
    use Dispatchable, SerializesModels;
    public $expense;
    public function __construct(Expense $expense) {
        $this->expense = $expense;
    }


    
}