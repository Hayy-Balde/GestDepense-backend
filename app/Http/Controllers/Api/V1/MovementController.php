<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Movement;
use Illuminate\Http\Request;

class MovementController extends Controller
{
    public function index(Request $request)
    {
        $limit = min((int) ($request->query('limit') ?? 200), 1000);

        $query = Movement::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc');

        if ($request->has('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->has('month') && $request->has('year')) {
            $query->whereYear('created_at', (int) $request->query('year'))
                ->whereMonth('created_at', (int) $request->query('month'));
        }

        if ($request->has('entity_type') && $request->has('entity_id')) {
            $entityType = $request->query('entity_type');
            $entityId = $request->query('entity_id');
            $query->where(function ($q) use ($entityType, $entityId) {
                $q->where(function ($inner) use ($entityType, $entityId) {
                    $inner->where('from_type', $entityType)->where('from_id', $entityId);
                })->orWhere(function ($inner) use ($entityType, $entityId) {
                    $inner->where('to_type', $entityType)->where('to_id', $entityId);
                });
            });
        }

        return response()->json($query->limit($limit)->get());
    }

    public function stats(Request $request)
    {
        $userId = $request->user()->id;

        return response()->json([
            'total_movements' => Movement::where('user_id', $userId)->count(),
            'by_type' => Movement::where('user_id', $userId)
                ->selectRaw('type, COUNT(*) as count, SUM(amount) as total')
                ->groupBy('type')
                ->get(),
        ]);
    }
}