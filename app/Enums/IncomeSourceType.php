<?php

declare(strict_types=1);

namespace App\Enums;

enum IncomeSourceType: string
{
    case SALARY = 'salary';
    case BUSINESS = 'business';
    case FREELANCE = 'freelance';
    case GIFT = 'gift';
    case INVESTMENT = 'investment';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::SALARY => 'Salaire',
            self::BUSINESS => 'Business',
            self::FREELANCE => 'Freelance',
            self::GIFT => 'Cadeau',
            self::INVESTMENT => 'Investissement',
            self::OTHER => 'Autre',
        };
    }
}
