<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $table = 'tasks';

    // Define status constants as numbers
    const STATUS_PENDING = 1;      // Task has been created but not yet started
    const STATUS_IN_PROGRESS = 2;  // Task is currently being worked on
    const STATUS_COMPLETED = 3;    // Task has been finished
    const STATUS_CANCELLED = 4;    // Task has been cancelled
    const STATUS_ON_HOLD = 5;      // Task is temporarily paused
    const STATUS_IN_REVIEW = 6;    // Task is under review

    // Map status numbers to their corresponding text labels
    public static $statusTexts = [
        self::STATUS_PENDING => 'pending',
        self::STATUS_IN_PROGRESS => 'in_progress',
        self::STATUS_COMPLETED => 'completed',
        self::STATUS_CANCELLED => 'cancelled',
        self::STATUS_ON_HOLD => 'on_hold',
        self::STATUS_IN_REVIEW => 'in_review',
    ];

    protected $fillable = [
        'name',
        'team_id',
        'description',
        'tags',
        'priority',
        'deadline',
        'status', // Status is stored as a number
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

    public function notes()
    {
        return $this->hasMany(TaskNote::class);
    }

    // Define the attachments relationship
    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * Get the text representation of the status.
     */
    public function getStatusTextAttribute()
    {
        return self::$statusTexts[$this->status] ?? 'unknown';
    }

    /**
     * Check if the task is overdue.
     */
    public function getIsOverdueAttribute()
    {
        // A task is overdue if:
        // 1. It has a deadline.
        // 2. The deadline has passed.
        return $this->deadline && now()->gt($this->deadline);
    }
}