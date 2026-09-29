<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

class DebtTypeController extends ConfigCrudController
{
    protected function model(): string
    {
        return \App\Models\DebtType::class;
    }

    protected function validationRules(bool $isUpdate): array
    {
        return [
            'value' => $isUpdate ? 'sometimes|string|max:50' : 'required|string|max:50|unique:debt_types,value',
            'label' => 'required|string|max:100',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ];
    }
}