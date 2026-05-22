<?php
namespace App\Helpers;
class MoneyHelper {
    public static function format($amount, $currency = "EUR") {
        return number_format($amount, 2) . " " . $currency;
    }
}