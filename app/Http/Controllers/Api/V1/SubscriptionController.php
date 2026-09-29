<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\CurrencyConverter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    public function __construct(protected CurrencyConverter $currencyConverter) {}

    public function index(Request $request)
    {
        $subs = Subscription::where('user_id', $request->user()->id)
            ->with('account:id,name,currency_code')
            ->get();
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
        return response()->json($sub->load('account:id,name,currency_code'), 201);
    }
    public function update(Request $request, string $id)
    {
        $user_id = $request->user()->id;
        $sub = Subscription::where('user_id',$user_id)->find($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric',
            'currency_code' => 'required|string|size:3',
            'billing_cycle' => 'required|string', // monthly, yearly, etc
            'next_billing_date' => 'required|date',
            'account_id' => 'nullable|uuid|exists:accounts,id',
            'is_active' => 'boolean',
        ]);
        
        $validated['user_id'] = $user_id;
        
        $sub->update($validated);
        return response()->json($sub->load('account:id,name,currency_code'), 201);
    }

    public function toggle(Request $request, string $id)
    {
        $user_id = $request->user()->id;
        $sub = Subscription::where('user_id',$user_id)->find($id);
        $sub->update(['is_active' => !$sub->is_active]);
        
        return response()->json($sub->load('account:id,name,currency_code'), 201);
    }

    public function pay(Request $request, $id)
    {
        return DB::transaction(function () use ($request, $id) {
            $sub = Subscription::where('user_id', $request->user()->id)->with('account')->findOrFail($id);

            if (! $sub->account_id) {
                throw ValidationException::withMessages([
                    'account_id' => ["Aucun compte n'est lié à cet abonnement."],
                ]);
            }

            $account = $sub->account;
            $amount = $this->currencyConverter->convert(
                (float) $sub->amount,
                $sub->currency_code,
                $account->currency_code
            );

            if ((float) $account->balance < $amount) {
                throw ValidationException::withMessages([
                    'amount' => ["Solde insuffisant sur le compte {$account->name}."],
                ]);
            }

            $account->decrement('balance', $amount);

            // Advance the billing date based on cycle
            if ($sub->billing_cycle->monthlyMultiplier() > 0) {
                $sub->next_billing_date = $sub->next_billing_date->copy()->addMonths($sub->billing_cycle->monthlyMultiplier());
            } else {
                $sub->next_billing_date = $sub->next_billing_date->copy()->addWeek();
            }
            $sub->save();

            return response()->json($sub->fresh()->load('account:id,name,currency_code'));
        });
    }

    public function destroy(Request $request, string $id)
    {
        $user_id = $request->user()->id;
        $sub = Subscription::where('user_id',$user_id)->find($id);
        $sub->delete();

        return response()->json($sub->load('account:id,name,currency_code'), 201);
    }
}