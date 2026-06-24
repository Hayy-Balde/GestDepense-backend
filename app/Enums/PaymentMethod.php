<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case BANK_TRANSFER = 'bank_transfer';
    case MOBILE_MONEY = 'mobile_money';
    case CREDIT_CARD = 'credit_card';
    case DEBIT_CARD = 'debit_card';
    case CHECK = 'check';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Espèces',
            self::BANK_TRANSFER => 'Virement Bancaire',
            self::MOBILE_MONEY => 'Mobile Money',
            self::CREDIT_CARD => 'Carte de Crédit',
            self::DEBIT_CARD => 'Carte de Débit',
            self::CHECK => 'Chèque',
            self::OTHER => 'Autre',
        };
    }
}
