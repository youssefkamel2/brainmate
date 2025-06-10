<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Task extends Model
{
    use HasFactory;

    protected $table = 'tasks';

    protected $casts = [
        'deadline' => 'datetime',
        'published_at' => 'datetime',
        'completed_at' => 'datetime',
        'is_backlog' => 'boolean',
    ];

    // Define status constants as numbers
    const STATUS_PENDING = 1;
    const STATUS_IN_PROGRESS = 2;
    const STATUS_COMPLETED = 3;
    const STATUS_CANCELLED = 4;
    const STATUS_ON_HOLD = 5;
    const STATUS_IN_REVIEW = 6;
    const STATUS_BACKLOG = 7; // New status for backlog items

    // Map status numbers to their corresponding text labels
    public static $statusTexts = [
        self::STATUS_PENDING => 'pending',
        self::STATUS_IN_PROGRESS => 'in_progress',
        self::STATUS_COMPLETED => 'completed',
        self::STATUS_CANCELLED => 'cancelled',
        self::STATUS_ON_HOLD => 'on_hold',
        self::STATUS_IN_REVIEW => 'in_review',
        self::STATUS_BACKLOG => 'backlog',
    ];

    protected $fillable = [
        'name',
        'team_id',
        'description',
        'tags',
        'priority',
        'deadline',
        'duration_days',
        'published_at',
        'is_backlog',
        'status',
        'completed_at',
        'created_at',
        'updated_at',
    ];

    protected $appends = ['status_text', 'is_overdue', 'is_completed', 'is_cancelled'];

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
     * Check if the task is completed.
     */
    public function getIsCompletedAttribute()
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the task is cancelled.
     */
    public function getIsCancelledAttribute()
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Check if the task is overdue.
     */
    public function getIsOverdueAttribute()
    {
        if (!$this->deadline || $this->is_backlog) {
            return false;
        }

        // If task is completed, check if it was completed after the deadline
        if ($this->status === self::STATUS_COMPLETED) {
            return $this->completed_at && $this->completed_at->greaterThan($this->deadline);
        }

        // If task is cancelled, it's not overdue
        if ($this->status === self::STATUS_CANCELLED) {
            return false;
        }

        // For active tasks, check if current time is past the deadline
        return now()->greaterThan($this->deadline);
    }

    /**
     * Scope a query to only include backlog tasks.
     */
    public function scopeBacklog($query)
    {
        return $query->where('is_backlog', true)
            ->orWhere('status', self::STATUS_BACKLOG);
    }

    /**
     * Scope a query to only include active (non-backlog) tasks.
     */
    public function scopeActive($query)
    {
        return $query->where('is_backlog', false)
            ->where('status', '!=', self::STATUS_BACKLOG);
    }

    /**
     * Publish a backlog task to make it active.
     */
    public static function publishBulk(array $taskIds)
    {
        return static::whereIn('id', $taskIds)
            ->where(function ($query) {
                $query->where('is_backlog', true)
                    ->orWhere('status', self::STATUS_BACKLOG);
            })
            ->update([
                'is_backlog' => false,
                'status' => self::STATUS_PENDING,
                'published_at' => now(),
                'deadline' => DB::raw('CASE WHEN duration_days > 0 THEN DATE_ADD(NOW(), INTERVAL duration_days DAY) ELSE NULL END'),
            ]);
    }

    /**
     * Mark the task as completed.
     */
    public function markAsCompleted()
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completed_at = now();
        $this->save();
    }

    /**
     * Mark the task as incomplete.
     */
    public function markAsIncomplete()
    {
        $this->status = self::STATUS_PENDING;
        $this->completed_at = null;
        $this->save();
    }
}
