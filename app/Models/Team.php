<?php 

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * App\Models\Team
 *
 * @property int $id
 * @property string $name
 * @property int $project_id
 * @property int $added_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $leader
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $members
 * @property-read int|null $members_count
 * @property-read \App\Models\Project $project
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Task> $tasks
 * @property-read int|null $tasks_count
 * @method static \Illuminate\Database\Eloquent\Builder|Team newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Team newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Team query()
 * @method static \Illuminate\Database\Eloquent\Builder|Team whereAddedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Team whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Team whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Team whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Team whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Team whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Team extends Model
{
    use HasFactory;

    protected $table = 'teams';

    protected $fillable = [
        'name',
        'added_by',
        'project_id',
        'team_code', 

    ];

    public static function generateTeamCode()
    {
        do {
            $code = Str::random(8); // Generate an 8-character random string
        } while (self::where('team_code', $code)->exists()); // Ensure it's unique

        return $code;
    }
        // Boot method to set the team code when creating a team
        protected static function boot()
        {
            parent::boot();
    
            static::creating(function ($team) {
                $team->team_code = self::generateTeamCode();
            });
        }
    public function tasks()
    {
        return $this->hasMany(Task::class);
    }


    public function members()
    {
        return $this->belongsToMany(User::class, 'project_role_user', 'team_id', 'user_id')
                    ->withPivot('role_id', 'project_id')
                    ->withTimestamps();
    }
    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
