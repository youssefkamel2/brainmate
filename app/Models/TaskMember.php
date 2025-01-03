<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
