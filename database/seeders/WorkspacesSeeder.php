<?php

namespace Database\Seeders;

use App\Models\Workspace;
use Illuminate\Database\Seeder;

class WorkspacesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        Workspace::create([
            'name' => 'Workpair Co',
            'images' => json_encode([
                'uploads/workspaces/workspace.jpg',
                'uploads/workspaces/workspace.jpg',
                'uploads/workspaces/workspace.jpg',
            ]),
            'location' => 'Cairo, Egypt',
            'map_url' => 'googlemapslocationlink',
            'phone' => '01145528803',
            'amenities' => 'wifi . coffee . meeting room',
            'rating' => 4.9,
            'price' => 125.00,
            'description' => 'A modern coworking space with high-speed internet, comfortable seating, and meeting rooms.',
            'wifi' => true,
            'coffee' => true,
            'meetingroom' => true,
            'silentroom' => false,
            'amusement' => false,
        ]);

        Workspace::create([
            'name' => 'Creative Space',
            'images' => json_encode([
                'uploads/workspaces/workspace.jpg',
                'uploads/workspaces/workspace.jpg',
                'uploads/workspaces/workspace.jpg',
            ]),
            'location' => 'Alexandria, Egypt',
            'map_url' => 'googlemapslocationlink',
            'phone' => '01145528803',
            'amenities' => 'wifi . printer . lounge',
            'rating' => 4.7,
            'price' => 150.00,
            'description' => 'A creative hub for freelancers and small teams, offering a relaxed atmosphere and printing services.',
            'wifi' => true,
            'coffee' => false,
            'meetingroom' => false,
            'silentroom' => true,
            'amusement' => true,
        ]);
    }
}