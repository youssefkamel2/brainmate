<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workspace extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'images',
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
        'images' => 'array', // Automatically decode JSON to array
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
        if (is_array($value)) {
            return array_map(function ($image) {
                return asset('uploads/workspaces/' . $image); // Generate full URL
            }, $value);
        }
        return [];
    }
}