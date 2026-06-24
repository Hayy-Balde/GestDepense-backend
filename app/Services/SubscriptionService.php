<?php

namespace App\Services;

use App\Models\Subscription;
use Carbon\Carbon;

class SubscriptionService
{
    public function createSubscription(array $data): Subscription
    {
        return Subscription::create($data);
    }

    public function calculateNextBillingDate(Subscription $subscription): Carbon
    {
        $currentDate = Carbon::parse($subscription->next_billing_date);
        
        switch ($subscription->billing_cycle) {
            case 'monthly':
                return $currentDate->addMonth();
            case 'yearly':
                return $currentDate->addYear();
            case 'weekly':
                return $currentDate->addWeek();
            default:
                return $currentDate->addMonth();
        }
    }

    public function renewSubscription(Subscription $subscription): void
    {
        $subscription->next_billing_date = $this->calculateNextBillingDate($subscription);
        $subscription->save();
        
        // This is where an Expense creation could automatically be triggered
        // if auto-pay is enabled.
    }
}
