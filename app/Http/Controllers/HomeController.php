<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    use ResponseTrait;

    /**
     * Get the To-Do List for the authenticated user.
     */
    public function getToDoList()
    {
        // Get the authenticated user
        $user = Auth::user();
    
        // Get tasks assigned to the user
        $tasks = Task::whereHas('members', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->whereIn('status', [1, 2, 5]) // Only include relevant statuses (pending, in_progress, overdue)
            ->get();
    
        // Check for overdue tasks and update their status
        foreach ($tasks as $task) {
            $task->checkAndUpdateOverdueStatus();
        }
    
        // Sort tasks by importance
        $sortedTasks = $tasks->sortByDesc(function ($task) {
            return $this->calculateTaskImportanceScore($task);
        });
    
        // Reset the keys to a sequential numeric array
        $sortedTasks = $sortedTasks->values();
    
        // Format the response
        $formattedTasks = $sortedTasks->map(function ($task) {
            return [
                'id' => $task->id,
                'name' => $task->name,
                'description' => $task->description,
                'tags' => $task->tags,
                'priority' => $task->priority,
                'deadline' => $task->deadline,
                'status' => $task->status, // Numeric status
                'status_text' => $this->getStatusText($task->status), // Add status text for readability
                'team_id' => $task->team_id,
            ];
        });
    
        return $this->success(['tasks' => $formattedTasks], 'To-Do list retrieved successfully.');
    }

    public function getTaskStatistics()
    {
        // Get the authenticated user
        $user = Auth::user();

        // Get all tasks assigned to the user
        $tasks = Task::whereHas('members', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->get();

        // Calculate the total number of tasks
        $totalTasks = $tasks->count();

        // Initialize counts for each status
        $completedCount = $tasks->where('status', Task::STATUS_COMPLETED)->count();
        $inProgressCount = $tasks->where('status', Task::STATUS_IN_PROGRESS)->count();
        $pendingCount = $tasks->where('status', Task::STATUS_PENDING)->count();

        // Calculate percentages
        $completedPercentage = $totalTasks > 0 ? ($completedCount / $totalTasks) * 100 : 0;
        $inProgressPercentage = $totalTasks > 0 ? ($inProgressCount / $totalTasks) * 100 : 0;
        $pendingPercentage = $totalTasks > 0 ? ($pendingCount / $totalTasks) * 100 : 0;

        // Format the response
        $statistics = [
            'completed' => [
                'count' => $completedCount,
                'percentage' => round($completedPercentage, 2), // Round to 2 decimal places
            ],
            'in_progress' => [
                'count' => $inProgressCount,
                'percentage' => round($inProgressPercentage, 2),
            ],
            'pending' => [
                'count' => $pendingCount,
                'percentage' => round($pendingPercentage, 2),
            ],
            'total_tasks' => $totalTasks,
        ];

        return $this->success(['statistics' => $statistics], 'Task statistics retrieved successfully.');
    }

    /**
     * Get the tasks that are in review for the authenticated user.
     */
    public function getInReviewTasks()
    {
        // Get the authenticated user
        $user = Auth::user();

        // Get tasks assigned to the user with status 'in_review'
        $tasks = Task::whereHas('members', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->where('status', Task::STATUS_IN_REVIEW)
            ->get();

        // Format the response
        $formattedTasks = $tasks->map(function ($task) {
            return [
                'id' => $task->id,
                'name' => $task->name,
                'description' => $task->description,
                'tags' => $task->tags,
                'priority' => $task->priority,
                'deadline' => $task->deadline,
                'status' => $task->status, // Numeric status
                'status_text' => $this->getStatusText($task->status), // Add status text for readability
                'team_id' => $task->team_id,
            ];
        });

        return $this->success(['tasks' => $formattedTasks], 'In-review tasks retrieved successfully.');
    }

    /**
     * Calculate the importance score for a task.
     */
    private function calculateTaskImportanceScore(Task $task)
    {
        // Priority weights
        $priorityWeights = [
            'high' => 3,
            'medium' => 2,
            'low' => 1,
        ];

        // Status weights
        $statusWeights = [
            'overdue' => 3,    // Overdue tasks are most important
            'pending' => 2,     // Pending tasks are next
            'in_progress' => 1, // In-progress tasks are least important
        ];

        // Map numeric status to string status
        $statusText = $this->getStatusText($task->status);

        // Deadline weight (closer deadlines have higher importance)
        $deadlineWeight = $task->deadline ? now()->diffInHours($task->deadline, false) : 0;

        // Improved deadline score calculation
        $deadlineScore = $deadlineWeight < 0 ? 100 : max(1, 100 / (max(1, $deadlineWeight / 24)));

        // Calculate the total score
        $priorityScore = $priorityWeights[$task->priority] ?? 0;
        $statusScore = $statusWeights[$statusText] ?? 0;
        $totalScore = $priorityScore + $statusScore + $deadlineScore;

        // // Log the scores for debugging
        // \Log::info('Task ID: ' . $task->id, [
        //     'priority' => $task->priority,
        //     'priority_score' => $priorityScore,
        //     'status' => $statusText,
        //     'status_score' => $statusScore,
        //     'deadline' => $task->deadline,
        //     'deadline_score' => $deadlineScore,
        //     'total_score' => $totalScore,
        // ]);

        return $totalScore;
    }

    /**
     * Map numeric status to string status.
     */
    private function getStatusText($status)
    {
        $statusMap = [
            1 => 'pending',
            2 => 'in_progress',
            3 => 'completed',
            4 => 'cancelled',
            5 => 'overdue',
            6 => 'on_hold',
            7 => 'in_review',
        ];

        return $statusMap[$status] ?? 'unknown';
    }
}