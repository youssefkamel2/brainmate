<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskNote extends Model
{
    use HasFactory;

    protected $table = 'task_notes'; // Ensure the correct table name is used.

    protected $fillable = [
        'task_id',
        'user_id',
        'note',
        'created_at',
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
