<?php

namespace App\Repositories;

use App\Models\Expense;
use App\Repositories\Interfaces\ExpenseRepositoryInterface;

class ExpenseRepository implements ExpenseRepositoryInterface
{
    public function getAll(array $filters)
    {
        $query = Expense::query()->with(['account', 'category', 'caisse', 'subCategory']);

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['account_id'])) {
            $query->where('account_id', $filters['account_id']);
        }

        if (!empty($filters['start_date'])) {
            $query->where('date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('date', '<=', $filters['end_date']);
        }

        return $query->latest('date')->paginate(15);
    }

    public function getById(string $id)
    {
        return Expense::findOrFail($id);
    }

    public function create(array $data)
    {
        return Expense::create($data);
    }

    public function update(string $id, array $data)
    {
        $expense = $this->getById($id);
        $expense->update($data);
        return $expense;
    }

    public function delete(string $id)
    {
        $expense = $this->getById($id);
        return $expense->delete();
    }
}
