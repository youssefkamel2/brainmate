<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Traits\ResponseTrait;

class NotificationController extends Controller
{
    use ResponseTrait;

    // Get all notifications for the authenticated user
    public function index()
    {
        $user = Auth::user();
        $notifications = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success($notifications, 'Notifications retrieved successfully.');
    }

    // Mark a notification as read
    public function markAsRead($notificationId)
    {
        $user = Auth::user();
        $notification = Notification::where('user_id', $user->id)
            ->where('id', $notificationId)
            ->first();

        if (!$notification) {
            return $this->error('Notification not found.', 404);
        }

        $notification->update(['read' => true]);

        return $this->success($notification, 'Notification marked as read.');
    }

    // Delete a notification
    public function destroy($notificationId)
    {
        $user = Auth::user();
        $notification = Notification::where('user_id', $user->id)
            ->where('id', $notificationId)
            ->first();

        if (!$notification) {
            return $this->error('Notification not found.', 404);
        }

        $notification->delete();

        return $this->success(null, 'Notification deleted successfully.');
    }
}