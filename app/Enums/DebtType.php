<?php

declare(strict_types=1);

namespace App\Enums;

enum DebtType: string
{
    case LENT = 'lent';
    case BORROWED = 'borrowed';

    public function label(): string
    {
        return match ($this) {
            self::LENT => 'Prêté',
            self::BORROWED => 'Emprunté',
        };
    }
}
