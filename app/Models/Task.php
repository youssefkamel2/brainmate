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
        'created_at',
        'updated_at',
    ];

    protected $appends = ['status_text', 'is_overdue'];

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
        // return true if the task is overdue and not completed or cancelled 
        return $this->deadline && !$this->is_backlog && !$this->is_completed && !$this->is_cancelled && now()->greaterThan($this->deadline);
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
}
