<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        'avatar',
        'status',
        'position', // Front-end, Back-end, Data Engineer, etc.
        'level', // Junior, Mid-level, Senior
        'skills', // Comma-separated string of skills
        'social', // Comma-separated string of social links
        'experience_years', // Years of experience
        'is_available', // Availability for new tasks
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
        'status' => 'boolean',
    ];

    public function getSkillsAttribute($value)
    {
        if (empty($value)) {
            return [];
        }

        // Convert comma-separated string to array
        return explode(',', $value);
    }

    public function getSocialAttribute($value)
    {
        if (empty($value)) {
            return [];
        }

        // Convert comma-separated string to array
        return explode(',', $value);
    }

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
     * Get the task members associated with the user.
     */
    public function taskMembers()
    {
        return $this->hasMany(TaskMember::class, 'user_id');
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
