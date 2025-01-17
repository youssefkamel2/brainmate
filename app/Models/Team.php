<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;

    protected $table = 'teams';

    protected $fillable = [
        'name',
        'leader_id',
        'project_id',
    ];

    public function leader()
    {
        return $this->belongsTo(User::class, 'leader_id');
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
