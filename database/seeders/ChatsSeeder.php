<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Chat;
use App\Models\User;
use App\Models\Team;

class ChatsSeeder extends Seeder
{
    public function run()
    {
        // Fetch all users and teams
        $users = User::all();
        $teams = Team::all();

        // Check if there are users and teams to seed chats
        if ($users->isEmpty()) {
            return;
        }

        // Seed individual (1-to-1) chats
        for ($i = 0; $i < 10; $i++) {
            $sender = $users->random();
            $receiver = $users->where('id', '!=', $sender->id)->random();

            Chat::create([
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
                'message' => 'This is a private message from ' . $sender->name . ' to ' . $receiver->name,
                'type' => 'text',
            ]);
        }

        // Seed group chats (linked to teams)
        foreach ($teams as $team) {
            for ($i = 0; $i < 5; $i++) {
                $sender = $users->random();

                Chat::create([
                    'sender_id' => $sender->id,
                    'team_id' => $team->id,
                    'message' => 'Team message from ' . $sender->name . ' in team ' . $team->name,
                    'type' => 'text',
                ]);
            }
        }
    }
}
