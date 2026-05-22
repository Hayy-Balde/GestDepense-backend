<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $subs = Subscription::where('user_id', $request->user()->id)->get();
        return response()->json($subs);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric',
            'currency_code' => 'required|string|size:3',
            'billing_cycle' => 'required|string', // monthly, yearly, etc
            'next_billing_date' => 'required|date',
            'account_id' => 'nullable|uuid|exists:accounts,id',
            'is_active' => 'boolean',
        ]);
        
        $validated['user_id'] = $request->user()->id;
        
        $sub = Subscription::create($validated);
        return response()->json($sub, 201);
    }
}
