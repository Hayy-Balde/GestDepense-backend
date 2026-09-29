<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

class BillingCycleController extends ConfigCrudController
{
    protected function model(): string
    {
        return \App\Models\BillingCycle::class;
    }

    protected function validationRules(bool $isUpdate): array
    {
        return [
            'value' => $isUpdate ? 'sometimes|string|max:50' : 'required|string|max:50|unique:billing_cycles,value',
            'label' => 'required|string|max:100',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ];
    }
}