<?php

namespace Database\Seeders;

use App\Models\Trash;
use Illuminate\Database\Seeder;

class TrashSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Trash::create([
            'user_id' => 1,
            'note_id' => 2,
        ]);
    }
}