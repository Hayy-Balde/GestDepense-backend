<?php
namespace App\DTOs;

class AccountDTO {
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly float $balance,
        public readonly string $currencyCode
    ) {}
}