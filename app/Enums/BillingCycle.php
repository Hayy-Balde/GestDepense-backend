<?php

declare(strict_types=1);

namespace App\Enums;

enum BillingCycle: string
{
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case YEARLY = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::WEEKLY => 'Hebdomadaire',
            self::MONTHLY => 'Mensuel',
            self::QUARTERLY => 'Trimestriel',
            self::YEARLY => 'Annuel',
        };
    }

    public function annualMultiplier(): float
    {
        return match ($this) {
            self::WEEKLY => 52,
            self::MONTHLY => 12,
            self::QUARTERLY => 4,
            self::YEARLY => 1,
        };
    }
}
