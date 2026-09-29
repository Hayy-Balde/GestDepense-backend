<?php

namespace App\Services;

use App\Models\ExchangeRate;

class CurrencyConverter
{
    public function convert(float $amount, string $from, string $to): float
    {
        if ($from === $to) {
            return $amount;
        }

        $rate = ExchangeRate::where('from_currency', $from)
            ->where('to_currency', $to)
            ->latest('fetched_at')
            ->value('rate');

        if ($rate !== null) {
            return $amount * (float) $rate;
        }

        // Try the inverse rate (1 / rate)
        $inverse = ExchangeRate::where('from_currency', $to)
            ->where('to_currency', $from)
            ->latest('fetched_at')
            ->value('rate');

        if ($inverse !== null && (float) $inverse > 0) {
            return $amount / (float) $inverse;
        }

        // Fallback: assume parity when no rate is available
        return $amount;
    }
}
