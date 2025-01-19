<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trash extends Model
{
    use HasFactory;

    protected $table = "trash";

    protected $fillable = [
        'user_id',
        'note_id',
    ];

    /**
     * Get the user that owns the trashed note.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the note that is trashed.
     */
    public function note()
    {
        return $this->belongsTo(PersonalNote::class);
    }
}