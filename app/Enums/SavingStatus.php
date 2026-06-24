<?php

declare(strict_types=1);

namespace App\Enums;

enum SavingStatus: string
{
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Actif',
            self::COMPLETED => 'Complété',
            self::CANCELLED => 'Annulé',
        };
    }
}
