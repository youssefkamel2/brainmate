<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'team_id',
        'message',
        'type',
        'media',
    ];

    /**
     * Relationships
     */

    // Sender of the message
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // Receiver of the message (nullable for group chats)
    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    // Team associated with the message (nullable for private chats)
    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    /**
     * Accessors/Mutators
     */

    // Get the message type in a readable format
    public function getTypeAttribute($value)
    {
        return ucfirst($value); // Text, File, Image
    }

    // Get the full URL for media files
    public function getMediaUrlAttribute()
    {
        return $this->media ? asset('storage/' . $this->media) : null;
    }
}
