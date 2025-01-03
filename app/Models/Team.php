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
        'created_at',
        'updated_at',
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
        return $this->belongsToMany(User::class, 'task_members', 'team_id', 'user_id')
                    ->withPivot('task_id', 'project_id')
                    ->withTimestamps();
    }
    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
