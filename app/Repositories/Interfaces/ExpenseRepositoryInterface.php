<?php

namespace App\Repositories\Interfaces;

interface ExpenseRepositoryInterface
{
    public function getAll(array $filters);
    public function getById(string $id);
    public function create(array $data);
    public function update(string $id, array $data);
    public function delete(string $id);
}
