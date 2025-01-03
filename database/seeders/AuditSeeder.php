<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Audit;
use Carbon\Carbon;

class AuditSeeder extends Seeder
{
    public function run()
    {
        // Sample data for auditing purposes
        $auditData = [
            [
                'table_name' => 'projects',
                'record_id' => 1,
                'action' => 'create',
                'old_values' => json_encode([]),
                'new_values' => json_encode([
                    'id' => 1,
                    'name' => 'Project 1',
                    'leader_id' => 1,
                    'description' => 'This is a test project for role-based permissions.',
                    'start_date' => '2024-12-02 00:45:40',
                    'end_date' => '2025-03-02 00:45:40',
                    'status' => 1,
                    'created_at' => '2024-12-02T00:45:40.000000Z',
                    'updated_at' => '2024-12-02T00:45:40.000000Z',
                ]),
                'user_id' => 1, // Assuming this user exists in the users table
                'created_at' => Carbon::now(),
            ],
            [
                'table_name' => 'tasks',
                'record_id' => 2,
                'action' => 'update',
                'old_values' => json_encode([
                    'id' => 2,
                    'name' => 'Task 1',
                    'status' => 'pending',
                ]),
                'new_values' => json_encode([
                    'id' => 2,
                    'name' => 'Task 1',
                    'status' => 'completed',
                ]),
                'user_id' => 2, // Assuming this user exists in the users table
                'created_at' => Carbon::now(),
            ],
            [
                'table_name' => 'team_spaces',
                'record_id' => 3,
                'action' => 'delete',
                'old_values' => json_encode([
                    'id' => 3,
                    'name' => 'Space 1',
                ]),
                'new_values' => json_encode([]),
                'user_id' => 1, // Assuming this user exists in the users table
                'created_at' => Carbon::now(),
            ]
        ];

        foreach ($auditData as $data) {
            Audit::create($data);
        }
    }
}
