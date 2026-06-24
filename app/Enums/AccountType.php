<?php

declare(strict_types=1);

namespace App\Enums;

enum AccountType: string
{
    case BANK = 'bank';
    case CASH = 'cash';
    case MOBILE_MONEY = 'mobile_money';
    case WALLET = 'wallet';
    case CRYPTO = 'crypto';
    case SAVINGS = 'savings';
    case CREDIT_CARD = 'credit_card';

    public function label(): string
    {
        return match ($this) {
            self::BANK => 'Compte Bancaire',
            self::CASH => 'Espèces',
            self::MOBILE_MONEY => 'Mobile Money',
            self::WALLET => 'Portefeuille',
            self::CRYPTO => 'Crypto',
            self::SAVINGS => 'Épargne',
            self::CREDIT_CARD => 'Carte de Crédit',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::BANK => 'building-2',
            self::CASH => 'banknote',
            self::MOBILE_MONEY => 'smartphone',
            self::WALLET => 'wallet',
            self::CRYPTO => 'bitcoin',
            self::SAVINGS => 'piggy-bank',
            self::CREDIT_CARD => 'credit-card',
        };
    }
}
