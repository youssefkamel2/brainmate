<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\Project
 *
 * @property int $id
 * @property string $name
 * @property string $start_date
 * @property string $end_date
 * @property string $description
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Collection|User[] $users
 * @property-read Collection|Team[] $teams
 * @property-read Collection|Task[] $tasks
 * @property int $status
 * @property-read int|null $tasks_count
 * @property-read int|null $teams_count
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder|Project newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Project newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Project query()
 * @method static \Illuminate\Database\Eloquent\Builder|Project whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Project whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Project whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Project whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Project whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Project whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description'
    ];

    /**
     * Get the users associated with the project.
     *
     * @return BelongsToMany|User
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'project_role_user')
            ->withPivot('role_id', 'team_id')
            ->withTimestamps();
    }

    /**
     * Get the tasks associated with the project.
     *
     * @return HasMany|Task
     */
    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
    /**
     * Get the teams associated with the project.
     *
     * @return HasMany|Team
     */
    public function teams()
    {
        return $this->hasMany(Team::class);
    }
}
