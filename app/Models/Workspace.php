<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workspace extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'images', // Stores JSON data as a string
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

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'images' => 'array', // Automatically decode JSON string to array
        'wifi' => 'boolean',
        'coffee' => 'boolean',
        'meetingroom' => 'boolean',
        'silentroom' => 'boolean',
        'amusement' => 'boolean',
        'rating' => 'float',
        'price' => 'float',
    ];
}