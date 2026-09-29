<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrencyController extends ConfigCrudController
{
    protected function model(): string
    {
        return Currency::class;
    }

    protected function validationRules(bool $isUpdate): array
    {
        $value = request()->route('value');
        return [
            'code' => $isUpdate ? 'sometimes|string|size:3' : 'required|string|size:3|unique:currencies,code',
            'name' => 'required|string|max:100',
            'symbol' => 'required|string|max:10',
            'decimal_places' => 'nullable|integer|min:0|max:4',
        ];
    }

    public function index(): JsonResponse
    {
        return response()->json(Currency::orderBy('code')->get());
    }
}