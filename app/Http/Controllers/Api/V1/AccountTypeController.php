<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

class AccountTypeController extends ConfigCrudController
{
    protected function model(): string
    {
        return \App\Models\AccountType::class;
    }

    protected function validationRules(bool $isUpdate): array
    {
        $value = request()->route('value');
        return [
            'value' => $isUpdate ? 'sometimes|string|max:50' : 'required|string|max:50|unique:account_types,value',
            'label' => 'required|string|max:100',
            'icon' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ];
    }
}