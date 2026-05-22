<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
// use App\Models\Notification;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        // Using Laravel's built-in notifications
        $notifications = $request->user()->notifications()->get();
        return response()->json($notifications);
    }

    public function read(Request $request, $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        return response()->json(['message' => 'Marked as read']);
    }
}
