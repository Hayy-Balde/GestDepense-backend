<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class ConfigCrudController extends Controller
{
    abstract protected function model(): string;

    protected function validationRules(bool $isUpdate): array
    {
        return [];
    }

    protected function uniqueRule(string $table, ?string $except): string
    {
        $rule = "unique:$table";
        if ($except) {
            $rule .= ",$except";
        }
        return $rule;
    }

    public function index(): JsonResponse
    {
        $model = $this->model();
        return response()->json($model::orderBy('sort_order')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $model = $this->model();
        $data = $request->validate($this->validationRules(false));

        $item = $model::create($data);
        return response()->json($item, 201);
    }

    public function show($value): JsonResponse
    {
        $model = $this->model();
        return response()->json($model::findOrFail($value));
    }

    public function update(Request $request, $value): JsonResponse
    {
        $model = $this->model();
        $item = $model::findOrFail($value);
        $data = $request->validate($this->validationRules(true));

        $item->update($data);
        return response()->json($item);
    }

    public function destroy($value): JsonResponse
    {
        $model = $this->model();
        $item = $model::findOrFail($value);
        $item->delete();
        return response()->json(null, 204);
    }
}
