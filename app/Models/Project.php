<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'leader_id', 'start_date', 'end_date', 'description'
    ];

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function leader()
    {
        return $this->belongsTo(User::class, 'leader_id');
    }
}
