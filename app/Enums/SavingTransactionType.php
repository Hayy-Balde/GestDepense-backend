<?php

declare(strict_types=1);

namespace App\Enums;

enum SavingTransactionType: string
{
    case DEPOSIT = 'deposit';
    case WITHDRAWAL = 'withdrawal';

    public function label(): string
    {
        return match ($this) {
            self::DEPOSIT => 'Versement',
            self::WITHDRAWAL => 'Retrait',
        };
    }
}
