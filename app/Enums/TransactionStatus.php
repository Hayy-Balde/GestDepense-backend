<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionStatus: string
{
    case COMPLETED = 'completed';
    case PENDING = 'pending';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::COMPLETED => 'Complété',
            self::PENDING => 'En attente',
            self::CANCELLED => 'Annulé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::COMPLETED => 'green',
            self::PENDING => 'yellow',
            self::CANCELLED => 'red',
        };
    }
}
