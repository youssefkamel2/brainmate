<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Folder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
    ];

    /**
     * Get the user that owns the folder.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the notes in this folder.
     */
    public function notes()
    {
        return $this->hasMany(PersonalNote::class);
    }
}