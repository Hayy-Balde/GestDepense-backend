<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $accounts = Account::where('user_id', $request->user()->id)->get();
        return response()->json($accounts);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'balance' => 'required|numeric',
            'currency_code' => 'required|string|size:3',
            'color' => 'nullable|string',
            'icon' => 'nullable|string',
        ]);
        
        $validated['user_id'] = $request->user()->id;
        $account = Account::create($validated);
        
        return response()->json($account, 201);
    }

    public function show(Request $request, $id)
    {
        $account = Account::where('user_id', $request->user()->id)->findOrFail($id);
        return response()->json($account);
    }

    public function update(Request $request, $id)
    {
        $account = Account::where('user_id', $request->user()->id)->findOrFail($id);
        $account->update($request->all());
        return response()->json($account);
    }

    public function destroy(Request $request, $id)
    {
        $account = Account::where('user_id', $request->user()->id)->findOrFail($id);
        $account->delete();
        return response()->json(null, 204);
    }
}
