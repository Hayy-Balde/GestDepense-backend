<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AccountType;
use App\Models\BillingCycle;
use App\Models\Category;
use App\Models\Currency;
use App\Models\DebtType;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'account_types' => AccountType::orderBy('sort_order')->get(),
            'payment_methods' => PaymentMethod::orderBy('sort_order')->get(),
            'billing_cycles' => BillingCycle::orderBy('sort_order')->get(),
            'debt_types' => DebtType::orderBy('sort_order')->get(),
            'currencies' => Currency::orderBy('code')->get(),
            'categories' => Category::where('is_system', true)
                ->orWhere('user_id', auth()->id())
                ->orderBy('sort_order')
                ->get(),
        ]);
    }
}
