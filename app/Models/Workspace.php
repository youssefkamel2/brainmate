<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workspace extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'images', // Comma-separated string of image paths
        'location',
        'map_url',
        'phone',
        'amenities',
        'rating',
        'price',
        'description',
        'wifi',
        'coffee',
        'meetingroom',
        'silentroom',
        'amusement',
    ];

    protected $casts = [
        'wifi' => 'boolean',
        'coffee' => 'boolean',
        'meetingroom' => 'boolean',
        'silentroom' => 'boolean',
        'amusement' => 'boolean',
    ];

    /**
     * Get the full URL for the images.
     */
    public function getImagesAttribute($value)
    {
        if (empty($value)) {
            return [];
        }

        // Split the comma-separated string into an array
        $imagePaths = explode(',', $value);

        // Generate full URLs for the images
        return array_map(function ($image) {
            return asset('uploads/workspaces/' . trim($image)); // Trim to remove any extra spaces
        }, $imagePaths);
    }
}