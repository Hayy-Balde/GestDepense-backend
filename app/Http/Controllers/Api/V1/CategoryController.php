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
        ]);
        
        $validated['user_id'] = $request->user()->id;
        $validated['is_system'] = false;
        
        $category = Category::create($validated);
        return response()->json($category, 201);
    }
    
    public function show(Request $request, $id)
    {
        $category = Category::where('user_id', $request->user()->id)->findOrFail($id);
        return response()->json($category);
    }

    public function update(Request $request, $id)
    {
        $category = Category::where('user_id', $request->user()->id)->findOrFail($id);
        $category->update($request->all());
        return response()->json($category);
    }

    public function destroy(Request $request, $id)
    {
        $category = Category::where('user_id', $request->user()->id)->findOrFail($id);
        $category->delete();
        return response()->json(null, 204);
    }
}
