<?php
namespace App\Helpers;
class DateHelper {
    public static function formatDate($date) {
        return \Carbon\Carbon::parse($date)->format("Y-m-d");
    }
}