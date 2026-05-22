<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;
class DebtReminderNotification extends Notification {
    public function via($notifiable) { return ["database"]; }
    public function toArray($notifiable) { return ["message" => "Debt Reminder"]; }
}