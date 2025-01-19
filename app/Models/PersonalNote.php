<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PersonalNote extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'user_id',
        'folder_id',
        'title',
        'content',
    ];

    /**
     * Get the user that owns the note.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the folder that the note belongs to.
     */
    public function folder()
    {
        return $this->belongsTo(Folder::class);
    }

    /**
     * Check if the note is favorited.
     */
    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * Check if the note is in the trash.
     */
    public function trash()
    {
        return $this->hasMany(Trash::class);
    }
}