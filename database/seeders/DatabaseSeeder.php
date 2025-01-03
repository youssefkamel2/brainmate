<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run()
    {
        $this->call([
            RolesSeeder::class,
            UsersSeeder::class,
            ProjectsSeeder::class,
            TeamsSeeder::class,
            TasksSeeder::class,
            NotificationsSeeder::class,
            AuditSeeder::class,
            AttachmentsSeeder::class,
            RemindersSeeder::class,
            TaskMembersSeeder::class,
            TaskNotesSeeder::class,
            ChatsSeeder::class,
            WorkspacesSeeder::class,
        ]);
    }
}
