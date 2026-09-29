<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Caisse;
use App\Models\Movement;
use App\Services\MovementService;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(protected MovementService $movements) {}

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
            'balance' => 'required|numeric|min:0',
            'currency_code' => 'required|string|size:3',
            'color' => 'nullable|string',
            'icon' => 'nullable|string',
        ]);

        $userId = $request->user()->id;
        $validated['user_id'] = $userId;

        $account = Account::create($validated);

        // The initial balance is the only time a direct amount is allowed.
        if ((float) $account->balance > 0) {
            $this->movements->record([
                'user_id' => $userId,
                'type' => Movement::TYPE_APPORT,
                'from_type' => 'external',
                'to_type' => 'account',
                'to_id' => $account->id,
                'amount' => $account->balance,
                'currency_code' => $account->currency_code,
                'label' => "Solde initial du compte « {$account->name} »",
            ]);
        }

        return response()->json($account, 201);
    }

    public function show(Request $request, $id)
    {
        $account = Account::where('user_id', $request->user()->id)->findOrFail($id);
        return response()->json($account);
    }

    public function update(Request $request, $id)
    {
        // Metadata only — money is handled via apport/regulation/transfer.
        $account = Account::where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'color' => 'sometimes|nullable|string',
            'icon' => 'sometimes|nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $account->update($validated);

        return response()->json($account->fresh());
    }

    public function apport(Request $request, $id)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'label' => 'nullable|string',
        ]);

        $userId = $request->user()->id;
        $account = Account::where('user_id', $userId)->findOrFail($id);

        $account = $this->movements->apportToAccount($userId, $account, (float) $validated['amount'], $validated['label'] ?? null);

        return response()->json(['account' => $account]);
    }

    public function regulate(Request $request, $id)
    {
        $validated = $request->validate([
            'balance' => 'required|numeric|min:0',
            'label' => 'nullable|string',
        ]);

        $userId = $request->user()->id;
        $account = Account::where('user_id', $userId)->findOrFail($id);

        $account = $this->movements->regulate($userId, $account, (float) $validated['balance'], $validated['label'] ?? null);

        return response()->json(['account' => $account]);
    }

    public function transfer(Request $request, $id)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'to_type' => 'required|in:account,caisse',
            'to_id' => 'required|uuid',
            'label' => 'nullable|string',
        ]);

        $userId = $request->user()->id;
        $from = Account::where('user_id', $userId)->findOrFail($id);
        $to = $validated['to_type'] === 'caisse'
            ? Caisse::where('user_id', $userId)->findOrFail($validated['to_id'])
            : Account::where('user_id', $userId)->findOrFail($validated['to_id']);

        [$from, $to] = $this->movements->transfer($userId, $from, $to, (float) $validated['amount'], $validated['label'] ?? null);

        return response()->json(['from' => $from, 'to' => $to]);
    }

    public function destroy(Request $request, $id)
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'mode' => 'required|in:lost,redirect',
            'account_id' => 'required_if:mode,redirect|nullable|uuid|exists:accounts,id',
        ]);

        $account = Account::where('user_id', $userId)->findOrFail($id);
        $dest = isset($validated['account_id'])
            ? Account::where('user_id', $userId)->findOrFail($validated['account_id'])
            : null;

        $this->movements->deleteResolve($userId, $account, $validated['mode'], $dest);

        return response()->json(null, 204);
    }
}