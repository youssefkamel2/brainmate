<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\TaskMember
 *
 * @property int $id
 * @property int $task_id
 * @property int $team_id
 * @property int $project_id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Task $task
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|TaskMember newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskMember newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskMember query()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskMember whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskMember whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskMember whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskMember whereTaskId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskMember whereTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskMember whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskMember whereUserId($value)
 * @mixin \Eloquent
 */
class TaskMember extends Model
{
    use HasFactory;

    protected $table = 'task_members'; // Ensure the correct table name is used.

    protected $fillable = [
        'task_id',
        'user_id',
        'team_id',
        'project_id',
        'created_at',
        'updated_at',
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
