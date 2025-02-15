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
    const STATUS_OVERDUE = 5;      // Task's deadline has passed and it was not completed
    const STATUS_ON_HOLD = 6;      // Task is temporarily paused
    const STATUS_IN_REVIEW = 7;    // Task is under review

    // Map status numbers to their corresponding text labels
    public static $statusTexts = [
        self::STATUS_PENDING => 'pending',
        self::STATUS_IN_PROGRESS => 'in_progress',
        self::STATUS_COMPLETED => 'completed',
        self::STATUS_CANCELLED => 'cancelled',
        self::STATUS_OVERDUE => 'overdue',
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

    /**
     * Check if the task is overdue and update its status.
     */
    public function checkAndUpdateOverdueStatus()
    {
        // If the task has no deadline, do nothing
        if (!$this->deadline) {
            return;
        }
    
        // Check if the task is overdue
        if (now()->gt($this->deadline)) {
            // If the task is not completed or cancelled, mark it as overdue
            if ($this->status !== self::STATUS_COMPLETED && $this->status !== self::STATUS_CANCELLED) {
                $this->status = self::STATUS_OVERDUE;
                $this->save();
            }
        } else {
            // If the task is overdue but the deadline is now in the future, revert to the previous status
            if ($this->status === self::STATUS_OVERDUE) {
                // Revert to the previous status (e.g., pending or in_progress)
                $this->status = self::STATUS_PENDING; // Or another appropriate status
                $this->save();
            }
        }
    }

    /**
     * Get the text representation of the status.
     */
    public function getStatusTextAttribute()
    {
        return self::$statusTexts[$this->status] ?? 'unknown';
    }
}