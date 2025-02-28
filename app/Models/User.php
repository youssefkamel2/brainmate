<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
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
        'phone',
        'gender',
        'birthdate',
        'bio',
        'avatar',
        'status',
        'position',
        'level',
        'skills',
        'social', // Ensure this is included
        'experience_years',
        'is_available',
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
        'birthdate' => 'date',
    ];

    public function getSkillsAttribute($value)
    {
        if (empty($value)) {
            return [];
        }

        // Convert comma-separated string to array
        return explode(',', $value);
    }


    /**
     * Convert the social field from text to an array.
     */
    public function getSocialAttribute($value)
    {
        if (empty($value)) {
            return [
                'facebook' => null,
                'github' => null,
                'linkedin' => null,
                'website' => null,
            ];
        }

        // Split the comma-separated string into individual links
        $links = explode(',', $value);

        // Convert links into a key-value array
        $socialLinks = [
            'facebook' => null,
            'github' => null,
            'linkedin' => null,
            'website' => null,
        ];

        foreach ($links as $link) {
            $parts = explode(':', $link, 2); // Split by the first colon
            if (count($parts) === 2) {
                $key = trim($parts[0]); // Key (e.g., "facebook")
                $value = trim($parts[1]); // Value (e.g., "https://facebook.com/johndoe")
                if (array_key_exists($key, $socialLinks)) {
                    $socialLinks[$key] = $value;
                }
            }
        }

        return $socialLinks;
    }

    /**
     * Convert the social field from an array to text.
     */
    public function setSocialAttribute($value)
    {
        if (is_array($value)) {
            $socialLinks = [];
            foreach ($value as $key => $link) {
                if (!empty($link)) {
                    $socialLinks[] = "$key:$link";
                }
            }
            $this->attributes['social'] = implode(',', $socialLinks);
        } else {
            $this->attributes['social'] = $value;
        }
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

    public function getRoleInTeam($projectId)
    {
        // Get the role for the user in the specified team
        $role = DB::table('project_role_user')
            ->where('user_id', $this->id)
            ->where('project_id', $projectId)
            ->first();
    
        if ($role) {
            $isManager = DB::table('project_role_user')
                ->where('user_id', $this->id)
                ->where('role_id', Role::ROLE_MANAGER)
                ->whereNull('team_id')
                ->exists();
    
            if ($isManager) {
                return 'manager';
            }
    
            if ($role->role_id == Role::ROLE_LEADER) {
                return 'leader';
            }
    
            if ($role->role_id == Role::ROLE_MEMBER) {
                return 'member';
            }
        }
    
        return null; // No role found
    }
}
