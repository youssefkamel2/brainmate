<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Reminder
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $task_id
 * @property string $reminder_time
 * @property string $message
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Task|null $task
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|Reminder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Reminder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Reminder query()
 * @method static \Illuminate\Database\Eloquent\Builder|Reminder whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Reminder whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Reminder whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Reminder whereReminderTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Reminder whereTaskId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Reminder whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Reminder whereUserId($value)
 * @mixin \Eloquent
 */
class Reminder extends Model
{
    use HasFactory;

    protected $table = 'reminders'; // Ensure the correct table name is used.

    protected $fillable = [
        'user_id',
        'task_id',
        'reminder_time',
        'message',
        'created_at',
        'updated_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
