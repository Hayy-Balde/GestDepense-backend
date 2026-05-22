<?php
namespace App\DTOs;

class IncomeDTO {
    public function __construct(
        public readonly string $title,
        public readonly float $amount,
        public readonly string $accountId,
        public readonly string $categoryId,
        public readonly string $date
    ) {}
}