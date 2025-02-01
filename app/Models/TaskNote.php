<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\TaskNote
 *
 * @property int $id
 * @property int $task_id
 * @property int $user_id
 * @property string $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Task $task
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|TaskNote newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskNote newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskNote query()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskNote whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskNote whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskNote whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskNote whereTaskId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskNote whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskNote whereUserId($value)
 * @mixin \Eloquent
 */
class TaskNote extends Model
{
    use HasFactory;

    protected $table = 'task_notes'; // Ensure the correct table name is used.

    protected $fillable = [
        'task_id',
        'user_id',
        'description',
        'created_at',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
