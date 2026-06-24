<?php

declare(strict_types=1);

namespace App\Enums;

enum DebtStatus: string
{
    case ACTIVE = 'active';
    case PARTIALLY_PAID = 'partially_paid';
    case PAID = 'paid';
    case OVERDUE = 'overdue';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Actif',
            self::PARTIALLY_PAID => 'Partiellement payé',
            self::PAID => 'Payé',
            self::OVERDUE => 'En retard',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'blue',
            self::PARTIALLY_PAID => 'yellow',
            self::PAID => 'green',
            self::OVERDUE => 'red',
        };
    }
}
