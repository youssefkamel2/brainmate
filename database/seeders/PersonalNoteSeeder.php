<?php

namespace Database\Seeders;

use App\Models\PersonalNote;
use Illuminate\Database\Seeder;

class PersonalNoteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create sample personal notes
        PersonalNote::create([
            'user_id' => 1, // Associate with user ID 1
            'folder_id' => 1, // Associate with folder ID 1
            'title' => 'Reflection on the Month of June',
            'content' => 'It’s hard to believe that June is already over! Looking back on the month, there were a few highlights that stand out to me.',
        ]);

        PersonalNote::create([
            'user_id' => 1, // Associate with user ID 1
            'folder_id' => 2, // Associate with folder ID 2
            'title' => 'My Favorite Recipes',
            'content' => 'I love cooking and trying new recipes. Here are some of my favorites...',
        ]);

        PersonalNote::create([
            'user_id' => 1, // Associate with user ID 1
            'folder_id' => null, // No folder assigned
            'title' => 'Thoughts on the Pandemic',
            'content' => 'It’s hard to believe that we’ve been living with the pandemic for over a year now...',
        ]);
    }
}