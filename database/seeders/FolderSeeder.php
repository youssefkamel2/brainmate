<?php

namespace Database\Seeders;

use App\Models\Folder;
use Illuminate\Database\Seeder;

class FolderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Folder::create([
            'user_id' => 1,
            'name' => 'Personal',
        ]);

        Folder::create([
            'user_id' => 1,
            'name' => 'Work',
        ]);
    }
}