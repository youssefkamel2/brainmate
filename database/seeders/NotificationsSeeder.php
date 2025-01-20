<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Notification;
use App\Models\User;

class NotificationsSeeder extends Seeder
{
    public function run()
    {
        $user = User::where('email', 'member@example.com')->first();

        Notification::create([
            'user_id' => $user->id,
            'message' => 'You have a task due tomorrow.',
            'read' => false,
        ]);
    }
}
