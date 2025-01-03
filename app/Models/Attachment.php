<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    use HasFactory;

    protected $table = 'attachments'; // Ensure the correct table name is used.

    protected $fillable = [
        'task_id', 
        'file_type', 
        'file_path', 
        'created_at', 
    ];

    // If you have relationships with other models, such as 'task', you can define them here.
    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
