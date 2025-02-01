<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Task
 *
 * @property int $id
 * @property string $name
 * @property int $team_id
 * @property string|null $description
 * @property string|null $tags
 * @property string $priority
 * @property string|null $deadline
 * @property int $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $members
 * @property-read int|null $members_count
 * @property-read \App\Models\Project|null $project
 * @property-read \App\Models\Team $team
 * @method static \Illuminate\Database\Eloquent\Builder|Task newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Task newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Task query()
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereDeadline($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereTags($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Task extends Model
{
    use HasFactory;

    protected $table = 'tasks';

    public static $statuses = [
        'pending',         // Task has been created but not yet started
        'in_progress',     // Task is currently being worked on
        'completed',       // Task has been finished
        'cancelled',       // Task has been cancelled
        // 'overdue',         // Task's deadline has passed and it was not completed
        'on_hold',         // Task is temporarily paused
        'in_review',       // Task is under review
    ];

    // Alternatively, you can define constants for each status
    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    // const STATUS_OVERDUE = 'overdue';
    const STATUS_ON_HOLD = 'on_hold';
    const STATUS_IN_REVIEW = 'in_review';

    protected $fillable = [
        'name',
        'team_id',
        'description',
        'tags',
        'priority',
        'deadline',
        'status',
        'created_at',
        'updated_at',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    // Get the members assigned to the task through task_members pivot table
    public function members()
    {
        return $this->belongsToMany(User::class, 'task_members')
                    ->withPivot('team_id', 'project_id')
                    ->withTimestamps();
    }

    public function notes()
    {
        return $this->hasMany(TaskNote::class);
    }
}
