<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;
class BudgetExceededNotification extends Notification {
    public function via($notifiable) { return ["database"]; }
    public function toArray($notifiable) { return ["message" => "Budget Exceeded"]; }
}