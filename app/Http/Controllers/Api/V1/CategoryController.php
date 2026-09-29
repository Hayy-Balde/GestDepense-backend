<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = Category::where('user_id', $request->user()->id)
                            ->orWhere('is_system', true)
                            ->orderBy('sort_order')
                            ->get();
        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:expense,income',
            'color' => 'nullable|string',
            'icon' => 'nullable|string',
            'slug' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer',
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['is_system'] = false;

        $category = Category::create($validated);
        return response()->json($category, 201);
    }

    public function show(Request $request, $id)
    {
        $category = Category::where(function ($q) use ($request) {
            $q->where('user_id', $request->user()->id)
              ->orWhere('is_system', true);
        })->findOrFail($id);
        return response()->json($category);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'sometimes|in:expense,income',
            'color' => 'nullable|string',
            'icon' => 'nullable|string',
            'slug' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer',
        ]);

        $category = Category::where(function ($q) use ($request) {
            $q->where('user_id', $request->user()->id)
              ->orWhere('is_system', true);
        })->findOrFail($id);
        $category->update($validated);
        return response()->json($category);
    }

    public function destroy(Request $request, $id)
    {
        $category = Category::where('user_id', $request->user()->id)
                            ->where('is_system', false)
                            ->findOrFail($id);
        $category->delete();
        return response()->json(null, 204);
    }
}
