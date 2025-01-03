<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $table = 'tasks';

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
}
