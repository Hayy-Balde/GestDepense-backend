<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Caisse;
use App\Services\MovementService;
use Illuminate\Http\Request;

class CaisseController extends Controller
{
    public function __construct(protected MovementService $movements) {}

    public function index(Request $request)
    {
        $caisses = Caisse::with('sourceAccount')
            ->where('user_id', $request->user()->id)
            ->get();
        return response()->json($caisses);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'budget_amount' => 'required|numeric|min:0.01',
            'source_account_id' => 'required|uuid|exists:accounts,id',
            'currency_code' => 'nullable|string|size:3',
            'icon' => 'nullable|string',
            'color' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $userId = $request->user()->id;
        $source = Account::where('user_id', $userId)->findOrFail($validated['source_account_id']);

        // La caisse porte sa propre devise : celle du compte source par défaut,
        // mais découplée, car une caisse doit pouvoir être alimentée depuis un
        // compte d'une autre devise (convertie par le taux en vigueur).
        $validated['currency_code'] = $validated['currency_code'] ?? $source->currency_code;

        $caisse = $this->movements->createAndFundCaisse($userId, $validated, $source);

        return response()->json(['caisse' => $caisse->load('sourceAccount')], 201);
    }

    public function show(Request $request, $id)
    {
        $caisse = Caisse::where('user_id', $request->user()->id)
            ->with('sourceAccount')
            ->findOrFail($id);
        return response()->json($caisse);
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
        $from = Caisse::where('user_id', $userId)->findOrFail($id);

        if ($from->status === 'closed') {
            return response()->json(['message' => 'Vous ne pouvez pas transférer depuis une caisse clôturée.'], 422);
        }

        $to = $validated['to_type'] === 'caisse'
            ? Caisse::where('user_id', $userId)->findOrFail($validated['to_id'])
            : Account::where('user_id', $userId)->findOrFail($validated['to_id']);

        [$from, $to] = $this->movements->transfer($userId, $from, $to, (float) $validated['amount'], $validated['label'] ?? null);

        return response()->json(['from' => $from, 'to' => $to]);
    }

    public function stats(Request $request, $id)
    {
        $caisse = Caisse::with('sourceAccount')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'caisse' => $caisse,
            'total_spent' => $caisse->spent_amount,
            'remaining_budget' => (float) $caisse->budget_amount - (float) $caisse->spent_amount,
            'percentage_used' => $caisse->percentage_used,
            'status' => $caisse->status,
        ]);
    }

    public function fund(Request $request, $id)
    {
        $validated = $request->validate([
            'source_account_id' => 'required|uuid|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'label' => 'nullable|string',
        ]);

        $userId = $request->user()->id;
        $caisse = Caisse::where('user_id', $userId)->findOrFail($id);
        $source = Account::where('user_id', $userId)->findOrFail($validated['source_account_id']);

        $caisse = $this->movements->fundCaisse($userId, $caisse, $source, (float) $validated['amount'], $validated['label'] ?? null);

        return response()->json(['caisse' => $caisse->load('sourceAccount')]);
    }

    public function close(Request $request, $id)
    {
        $validated = $request->validate([
            'account_id' => 'nullable|uuid|exists:accounts,id',
        ]);

        $userId = $request->user()->id;
        $caisse = Caisse::where('user_id', $userId)->findOrFail($id);
        $dest = isset($validated['account_id'])
            ? Account::where('user_id', $userId)->findOrFail($validated['account_id'])
            : null;

        $caisse = $this->movements->closeCaisse($userId, $caisse, $dest);

        return response()->json(['caisse' => $caisse->load('sourceAccount')]);
    }

    public function update(Request $request, $id)
    {
        // Update is restricted to metadata only (money is handled via fund/regulate/close).
        $caisse = Caisse::where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'icon' => 'sometimes|nullable|string',
            'color' => 'sometimes|nullable|string',
            'description' => 'sometimes|nullable|string',
        ]);

        $caisse->update($validated);

        return response()->json(['caisse' => $caisse->fresh()->load('sourceAccount')]);
    }

    public function destroy(Request $request, $id)
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'mode' => 'required|in:lost,redirect',
            'account_id' => 'required_if:mode,redirect|nullable|uuid|exists:accounts,id',
        ]);

        $caisse = Caisse::where('user_id', $userId)->findOrFail($id);
        $dest = isset($validated['account_id'])
            ? Account::where('user_id', $userId)->findOrFail($validated['account_id'])
            : null;

        $this->movements->deleteResolve($userId, $caisse, $validated['mode'], $dest);

        return response()->json(null, 204);
    }
}