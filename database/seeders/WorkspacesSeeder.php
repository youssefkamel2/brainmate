<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Workspace;

class WorkspacesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $workspaces = [
            [
                'name' => 'Downtown Workspace',
                'image' => 'workspaces/downtown.jpg',
                'location' => '123 Main Street, City Center',
                'rate' => 4.5,
                'price' => 50.00,
                'phone_number' => '555-1234',
            ],
            [
                'name' => 'Suburban Workspace',
                'image' => 'workspaces/suburban.jpg',
                'location' => '456 Elm Street, Suburbia',
                'rate' => 4.0,
                'price' => 40.00,
                'phone_number' => '555-5678',
            ],
            [
                'name' => 'Coastal Workspace',
                'image' => 'workspaces/coastal.jpg',
                'location' => '789 Beach Drive, Seaside',
                'rate' => 5.0,
                'price' => 75.00,
                'phone_number' => '555-9012',
            ],
        ];

        foreach ($workspaces as $workspace) {
            Workspace::create($workspace);
        }
    }
}
