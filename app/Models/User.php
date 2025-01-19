<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;


/**
 * App\Models\User
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $avatar
 * @property bool $status
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Collection|Role[] $roles
 * @property-read Collection|Project[] $projects
 * @property-read Collection|Team[] $teams
 * @property string|null $remember_token
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read int|null $projects_count
 * @property-read int|null $roles_count
 * @property-read Collection<int, \App\Models\Task> $tasks
 * @property-read int|null $tasks_count
 * @property-read int|null $teams_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User query()
 * @method static \Illuminate\Database\Eloquent\Builder|User whereAvatar($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereUpdatedAt($value)
 * @mixin \Eloquent
 */

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Get the identifier that will be stored in the JWT subject claim.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key-value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Get the roles associated with the user.
     *
     * @return BelongsToMany|Role
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'project_role_user')
            ->withPivot('project_id', 'team_id')
            ->withTimestamps();
    }

    /**
     * Get the tasks associated with the user.
     *
     * @return BelongsToMany|Team
     */
    public function tasks()
    {
        return $this->belongsToMany(Task::class, 'task_members', 'user_id', 'task_id')
            ->withPivot('team_id', 'project_id')
            ->withTimestamps();
    }

    /**
     * Get the teams associated with the user.
     *
     * @return BelongsToMany|Team
     */
    public function teams()
    {
        return $this->belongsToMany(Team::class, 'project_role_user', 'user_id', 'team_id')
            ->withPivot('role_id', 'project_id')
            ->withTimestamps();
    }

    /**
     * Get the projects associated with the user.
     *
     * @return BelongsToMany|Project
     */
    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_role_user')
            ->withPivot('role_id', 'team_id')
            ->withTimestamps();
    }
}
