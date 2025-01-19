<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Chat
 *
 * @property int $id
 * @property int $sender_id
 * @property int|null $receiver_id
 * @property int|null $team_id
 * @property string $message
 * @property string $type
 * @property string|null $media
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $media_url
 * @property-read \App\Models\User|null $receiver
 * @property-read \App\Models\User $sender
 * @property-read \App\Models\Team|null $team
 * @method static \Illuminate\Database\Eloquent\Builder|Chat newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Chat newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Chat query()
 * @method static \Illuminate\Database\Eloquent\Builder|Chat whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Chat whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Chat whereMedia($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Chat whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Chat whereReceiverId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Chat whereSenderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Chat whereTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Chat whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Chat whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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
